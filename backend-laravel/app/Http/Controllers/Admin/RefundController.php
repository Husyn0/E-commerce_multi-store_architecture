<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\Order;
use App\Models\Refund;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RefundController extends TenantApiController
{
    public function store(
        Request $request,
        string $orderId,
        PaymentGatewayManager $gateways,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 255 || preg_match('/^[\x21-\x7E]+$/D', $key) !== 1) {
            return response()->json(['message' => 'A valid Idempotency-Key header is required.'], 400);
        }

        $input = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $tenantId = $this->tenantId();
        $settings = TenantSettings::query()->forTenant($tenantId)->first();
        if ($settings === null) {
            return response()->json(['message' => 'Payment processing is not configured for this store.'], 503);
        }
        try {
            $gateway = $gateways->forTenant($settings);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Refund processing is not configured.'], 503);
        }

        $refundId = $this->idempotentUuid($tenantId, $key);
        $result = DB::transaction(function () use ($auditLogger, $input, $key, $orderId, $refundId, $request, $settings, $tenantId): array {
            $order = Order::query()
                ->forTenant($tenantId)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();
            if ($order === null) {
                return ['not_found' => true];
            }

            $refund = Refund::query()
                ->forTenant($tenantId)
                ->whereKey($refundId)
                ->lockForUpdate()
                ->first();
            $reason = isset($input['reason']) ? trim($input['reason']) : null;
            if ($refund !== null) {
                if ($refund->order_id !== $order->id
                    || (int) $refund->amount_cents !== (int) $input['amount_cents']
                    || $refund->reason !== $reason) {
                    return ['conflict' => true];
                }
                if ($refund->status === 'failed') {
                    return ['failed' => true];
                }

                return ['refund' => $refund, 'order' => $order, 'created' => false];
            }

            if ($order->payment_status !== 'succeeded' || $order->payment_intent_id === null) {
                return ['not_refundable' => true];
            }
            if ($order->payment_provider !== $settings->payment_driver) {
                return ['provider_mismatch' => true];
            }

            $committedOrPending = (int) Refund::query()
                ->forTenant($tenantId)
                ->where('order_id', $order->id)
                ->whereIn('status', ['pending', 'succeeded'])
                ->sum('amount_cents');
            $remaining = (int) $order->total_cents - $committedOrPending;
            if ((int) $input['amount_cents'] > $remaining) {
                return ['amount_exceeded' => true];
            }

            $refund = new Refund;
            $refund->id = $refundId;
            $refund->tenant_id = $tenantId;
            $refund->order_id = $order->id;
            $refund->amount_cents = (int) $input['amount_cents'];
            $refund->reason = $reason;
            $refund->status = 'pending';
            $refund->save();

            $actor = $request->user();
            if (! $actor instanceof User) {
                throw new RuntimeException('Authenticated Admin user is missing.');
            }
            $auditLogger->web(
                $this->tenantContext(),
                $actor,
                'refund.created',
                'refund',
                $refund->id,
                $request,
                [
                    'order_id' => $order->id,
                    'amount_cents' => (int) $refund->amount_cents,
                    'idempotency_key_hash' => hash('sha256', $key),
                ],
            );

            return ['refund' => $refund, 'order' => $order, 'created' => true];
        });

        if (isset($result['not_found'])) {
            return response()->json(['message' => 'Order not found.'], 404);
        }
        if (isset($result['conflict'])) {
            return response()->json(['message' => 'Idempotency-Key was already used with different refund details.'], 409);
        }
        if (isset($result['failed'])) {
            return response()->json(['message' => 'This refund failed at the provider; use a new Idempotency-Key to retry.'], 409);
        }
        if (isset($result['not_refundable'])) {
            return response()->json(['message' => 'Only successfully paid orders can be refunded.'], 409);
        }
        if (isset($result['provider_mismatch'])) {
            return response()->json([
                'message' => 'The payment configuration changed; restore the order provider before issuing a refund.',
            ], 503);
        }
        if (isset($result['amount_exceeded'])) {
            return response()->json(['message' => 'Refund amount exceeds the remaining refundable balance.'], 422);
        }

        try {
            $providerRefund = $gateway->createRefund(
                $settings,
                $result['order'],
                $result['refund'],
                'refund_'.$result['refund']->id,
            );
        } catch (PaymentProviderException $exception) {
            report($exception);

            return response()->json([
                'message' => 'The refund request could not be completed. Retry with the same Idempotency-Key.',
            ], 502);
        }

        $refund = DB::transaction(function () use ($providerRefund, $result, $tenantId): Refund {
            $refund = Refund::query()
                ->forTenant($tenantId)
                ->whereKey($result['refund']->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($refund->provider_refund_id !== null && $refund->provider_refund_id !== $providerRefund->id) {
                throw new RuntimeException('Payment provider returned a different refund for the same key.');
            }
            $refund->provider_refund_id = $providerRefund->id;
            $refund->save();

            return $refund;
        });

        return response()->json([
            'id' => $refund->id,
            'order_id' => $refund->order_id,
            'amount_cents' => (int) $refund->amount_cents,
            'status' => $refund->status,
            'reason' => $refund->reason,
        ], $result['created'] ? 202 : 200)->header('Cache-Control', 'no-store');
    }

    private function idempotentUuid(string $tenantId, string $key): string
    {
        $namespace = hex2bin(str_replace('-', '', $tenantId));
        if ($namespace === false || strlen($namespace) !== 16) {
            throw new RuntimeException('Invalid tenant identifier.');
        }

        $hex = substr(hash('sha1', $namespace.$key), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
