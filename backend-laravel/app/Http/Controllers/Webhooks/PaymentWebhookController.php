<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentWebhookEvent;
use App\Models\Refund;
use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController
{
    private const MAX_ATTEMPTS = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $isStripe = $request->header('Stripe-Signature') !== null;
        if ($isStripe) {
            if (! $this->validStripeSignature($rawBody, (string) $request->header('Stripe-Signature'))) {
                return response()->json(['message' => 'Invalid webhook signature.'], 400);
            }
            $provider = 'stripe';
        } else {
            if (app()->isProduction()) {
                return response()->json(['message' => 'Demo webhooks are disabled in production.'], 503);
            }
            $secret = (string) config('services.demo_webhook_secret');
            $signature = (string) $request->header('X-Demo-Signature');
            if (strlen($secret) < 32) {
                return response()->json(['message' => 'Webhook processing is not configured.'], 503);
            }
            if (! hash_equals(hash_hmac('sha256', $rawBody, $secret), $signature)) {
                return response()->json(['message' => 'Invalid webhook signature.'], 400);
            }
            $provider = 'demo';
        }

        $eventId = $payload['id'] ?? null;
        $eventType = $payload['type'] ?? null;
        if (! is_string($eventId) || $eventId === ''
            || ! is_string($eventType)
            || strlen($eventId) > 255
            || strlen($eventType) > 120
            || ! $this->isTenantPaymentEvent($eventType)) {
            return response()->json(['message' => 'Unsupported payment event.'], 400);
        }

        $tenantSettings = $this->resolveSettings($provider, $payload);
        if ($tenantSettings === null) {
            Log::warning('Rejected payment webhook with unknown tenant account.', [
                'provider' => $provider,
                'provider_event_id' => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['message' => 'Unknown payment account.'], 404);
        }

        if ($provider === 'demo' && ! str_starts_with($eventId, $tenantSettings->tenant_id.':')) {
            return response()->json(['message' => 'Demo event IDs must include their tenant scope.'], 400);
        }

        try {
            $event = DB::transaction(function () use ($eventId, $eventType, $payload, $provider, $tenantSettings): ?PaymentWebhookEvent {
                $event = PaymentWebhookEvent::query()
                    ->forTenant($tenantSettings->tenant_id)
                    ->where('provider', $provider)
                    ->where('provider_event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($event !== null) {
                    if ($event->status !== 'failed' || $event->attempts >= self::MAX_ATTEMPTS) {
                        return null;
                    }
                    $event->status = 'processing';
                    $event->attempts++;
                    $event->last_error = null;
                    $event->locked_at = now();
                    $event->save();
                } else {
                    $tenant = Tenant::query()->whereKey($tenantSettings->tenant_id)->firstOrFail();
                    $event = new PaymentWebhookEvent;
                    $event->tenant_id = $tenant->id;
                    $event->provider = $provider;
                    $event->provider_event_id = $eventId;
                    $event->event_type = $eventType;
                    $event->payload = $payload;
                    $event->suspended_at_receipt = $tenant->status === 'suspended';
                    $event->status = 'processing';
                    $event->attempts = 1;
                    $event->locked_at = now();
                    $event->save();
                }

                try {
                    DB::transaction(function () use ($tenantSettings, $provider, $eventId, $eventType, $payload, $event): void {
                        $this->applyPaymentEvent(
                            $tenantSettings->tenant_id,
                            $provider,
                            $eventId,
                            $eventType,
                            $payload,
                            $event,
                        );
                    });
                } catch (QueryException) {
                    $event->status = 'failed';
                    $event->last_error = 'Payment event processing failed.';
                    $event->locked_at = null;
                    $event->save();

                    return $event;
                }
                $event->status = 'processed';
                $event->processed_at = now();
                $event->locked_at = null;
                $event->last_error = null;
                $event->save();

                return $event;
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 1062) {
                throw $exception;
            }

            return response()->json(['status' => 'duplicate']);
        }

        return response()->json(['status' => $event === null ? 'duplicate' : 'processed']);
    }

    private function validStripeSignature(string $body, string $header): bool
    {
        $secret = (string) config('services.stripe.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && $value !== null) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if (! is_string($timestamp) || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > 300
            || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function isTenantPaymentEvent(string $eventType): bool
    {
        return str_starts_with($eventType, 'payment_intent.')
            || str_starts_with($eventType, 'charge.')
            || str_starts_with($eventType, 'refund.');
    }

    private function resolveSettings(string $provider, array $payload): ?TenantSettings
    {
        if ($provider === 'stripe') {
            $accountId = $payload['account'] ?? null;
            if (! is_string($accountId) || $accountId === '') {
                return null;
            }

            return TenantSettings::query()->where('stripe_account_id', $accountId)->first();
        }

        $tenantId = $payload['tenant_id'] ?? null;
        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return TenantSettings::query()->forTenant($tenantId)->first();
    }

    private function applyPaymentEvent(
        string $tenantId,
        string $provider,
        string $eventId,
        string $eventType,
        array $payload,
        PaymentWebhookEvent $event,
    ): void {
        $object = $payload['data']['object'] ?? null;
        if (! is_array($object)) {
            return;
        }

        if (str_starts_with($eventType, 'payment_intent.')) {
            $intentId = $object['id'] ?? null;
            if (! is_string($intentId)) {
                return;
            }

            $metadataOrderId = $object['metadata']['order_id'] ?? null;
            $orderQuery = Order::query()
                ->forTenant($tenantId)
                ->where('payment_provider', $provider);
            $order = is_string($metadataOrderId)
                ? (clone $orderQuery)->whereKey($metadataOrderId)->lockForUpdate()->first()
                : null;
            $order ??= $orderQuery->where('payment_intent_id', $intentId)->lockForUpdate()->first();
            if ($order === null) {
                return;
            }
            if ($order->payment_intent_id !== null && $order->payment_intent_id !== $intentId) {
                return;
            }

            $nextStatus = match ($eventType) {
                'payment_intent.succeeded' => 'succeeded',
                'payment_intent.payment_failed', 'payment_intent.canceled' => 'failed',
                default => null,
            };
            if ($nextStatus === null || $order->payment_status === $nextStatus) {
                return;
            }
            $order->payment_intent_id ??= $intentId;
            if (! in_array($order->payment_status, ['requires_payment', 'processing'], true)
                && ! ($nextStatus === 'succeeded' && $order->payment_status === 'failed')) {
                return;
            }

            $order->payment_status = $nextStatus;
            if ($nextStatus === 'succeeded') {
                $order->paid_at = now();
            }
            $order->save();
            $this->writeWebhookAudit($tenantId, $order->id, 'order', 'order.updated', $provider, $eventId, $eventType, $event);

            return;
        }

        if (str_starts_with($eventType, 'charge.')) {
            if ($eventType === 'charge.refunded') {
                $refunds = $object['refunds']['data'] ?? [];
                if (is_array($refunds)) {
                    foreach ($refunds as $refund) {
                        if (is_array($refund)) {
                            $refund['payment_intent'] ??= $object['payment_intent'] ?? null;
                            $this->applyRefund(
                                $tenantId,
                                $provider,
                                $eventId,
                                'refund.succeeded',
                                $refund,
                                $event,
                            );
                        }
                    }
                }

                return;
            }

            $intentId = $object['payment_intent'] ?? null;
            if (! is_string($intentId)) {
                return;
            }
            $nextStatus = match ($eventType) {
                'charge.succeeded' => 'succeeded',
                'charge.failed' => 'failed',
                default => null,
            };
            if ($nextStatus !== null) {
                $this->updateOrderPaymentStatus($tenantId, $provider, $eventId, $eventType, $intentId, $nextStatus, $event);
            }

            return;
        }

        if (str_starts_with($eventType, 'refund.')) {
            $this->applyRefund($tenantId, $provider, $eventId, $eventType, $object, $event);
        }
    }

    private function applyRefund(
        string $tenantId,
        string $provider,
        string $eventId,
        string $eventType,
        array $object,
        PaymentWebhookEvent $event,
    ): void {
        $providerRefundId = $object['id'] ?? null;
        $intentId = $object['payment_intent'] ?? null;
        $amount = $object['amount'] ?? null;
        if (! is_string($providerRefundId) || ! is_string($intentId) || ! is_int($amount) || $amount <= 0) {
            return;
        }

        $order = Order::query()
            ->forTenant($tenantId)
            ->where('payment_provider', $provider)
            ->where('payment_intent_id', $intentId)
            ->lockForUpdate()
            ->first();
        if ($order === null) {
            return;
        }

        $status = match ($object['status'] ?? $eventType) {
            'succeeded', 'refund.succeeded' => 'succeeded',
            'failed', 'canceled', 'refund.failed' => 'failed',
            default => 'pending',
        };
        $localRefundId = $object['metadata']['local_refund_id'] ?? null;
        $refundQuery = Refund::query()->forTenant($tenantId);
        $refund = is_string($localRefundId)
            ? (clone $refundQuery)->whereKey($localRefundId)->lockForUpdate()->first()
            : null;
        $refund ??= $refundQuery->where('provider_refund_id', $providerRefundId)->lockForUpdate()->first();
        $created = $refund === null;
        if ($created) {
            $refund = new Refund;
            $refund->tenant_id = $tenantId;
            $refund->order_id = $order->id;
            $refund->provider_refund_id = $providerRefundId;
            $refund->amount_cents = $amount;
            $refund->status = $status;
            $refund->save();
        } elseif ($refund->order_id === $order->id
            && (int) $refund->amount_cents === $amount
            && ($refund->provider_refund_id === null || $refund->provider_refund_id === $providerRefundId)
            && ($refund->status === $status
                || ($refund->status === 'pending' && in_array($status, ['succeeded', 'failed'], true))
                || ($refund->status === 'failed' && $status === 'succeeded')
                || $refund->provider_refund_id === null)) {
            $statusChanged = $refund->status !== $status;
            $refund->provider_refund_id = $providerRefundId;
            $refund->status = $status;
            $refund->save();
        } else {
            return;
        }

        if ($created || $statusChanged) {
            $action = $status === 'pending' ? 'refund.created' : 'refund.'.$status;
            $this->writeWebhookAudit(
                $tenantId,
                $refund->id,
                'refund',
                $action,
                $provider,
                $eventId,
                $eventType,
                $event,
            );
        }

        $refundedTotal = Refund::query()
            ->forTenant($tenantId)
            ->where('order_id', $order->id)
            ->where('status', 'succeeded')
            ->sum('amount_cents');
        if ((int) $refundedTotal >= (int) $order->total_cents && $order->payment_status === 'succeeded') {
            $order->payment_status = 'refunded';
            $order->save();
        }
    }

    private function updateOrderPaymentStatus(
        string $tenantId,
        string $provider,
        string $eventId,
        string $eventType,
        string $intentId,
        string $nextStatus,
        PaymentWebhookEvent $event,
    ): void {
        $order = Order::query()
            ->forTenant($tenantId)
            ->where('payment_provider', $provider)
            ->where('payment_intent_id', $intentId)
            ->lockForUpdate()
            ->first();
        if ($order === null || $order->payment_status === $nextStatus
            || ! in_array($order->payment_status, ['requires_payment', 'processing'], true)) {
            return;
        }

        $order->payment_status = $nextStatus;
        if ($nextStatus === 'succeeded') {
            $order->paid_at = now();
        }
        $order->save();
        $this->writeWebhookAudit($tenantId, $order->id, 'order', 'order.updated', $provider, $eventId, $eventType, $event);
    }

    private function writeWebhookAudit(
        string $tenantId,
        string $entityId,
        string $entityType,
        string $action,
        string $provider,
        string $eventId,
        string $eventType,
        PaymentWebhookEvent $event,
    ): void {
        $audit = new AuditLog;
        $audit->tenant_id = $tenantId;
        $audit->actor_id = null;
        $audit->action = $action;
        $audit->entity_type = $entityType;
        $audit->entity_id = $entityId;
        $audit->source = 'webhook';
        $audit->host_type = null;
        $audit->metadata = [
            'provider' => $provider,
            'provider_event_id' => $eventId,
            'event_type' => $eventType,
            'suspended_at_receipt' => $event->suspended_at_receipt,
        ];
        $audit->save();
    }
}
