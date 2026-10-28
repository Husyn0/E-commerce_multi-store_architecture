<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Refund;
use App\Models\TenantSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentGateway implements PaymentGateway
{
    public function createIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentIntentResult {
        $secret = (string) config('services.stripe.secret');
        if (! str_starts_with($secret, 'sk_')) {
            throw new RuntimeException('Stripe is not configured for payments.');
        }
        if (! is_string($settings->stripe_account_id) || $settings->stripe_account_id === '') {
            throw new RuntimeException('Tenant Stripe account is not configured.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withToken($secret)
                ->withHeaders([
                    'Stripe-Account' => $settings->stripe_account_id,
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount' => (int) $order->total_cents,
                    'currency' => strtolower($order->currency),
                    'automatic_payment_methods[enabled]' => 'true',
                    'metadata[tenant_id]' => $settings->tenant_id,
                    'metadata[order_id]' => $order->id,
                    'metadata[order_number]' => $order->order_number,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            throw new PaymentProviderException('The payment provider could not complete the request.');
        }

        if (! is_array($response)
            || ! is_string($response['id'] ?? null)
            || ! is_string($response['client_secret'] ?? null)
            || ! is_string($response['status'] ?? null)) {
            throw new PaymentProviderException('The payment provider returned an invalid response.');
        }

        return new PaymentIntentResult(
            $response['id'],
            $response['client_secret'],
            $response['status'],
        );
    }

    public function createRefund(
        TenantSettings $settings,
        Order $order,
        Refund $refund,
        string $idempotencyKey,
    ): PaymentRefundResult {
        $secret = (string) config('services.stripe.secret');
        if (! str_starts_with($secret, 'sk_')
            || ! is_string($settings->stripe_account_id)
            || $settings->stripe_account_id === ''
            || ! is_string($order->payment_intent_id)
            || $order->payment_intent_id === '') {
            throw new PaymentProviderException('Stripe refunds are not configured for this order.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withToken($secret)
                ->withHeaders([
                    'Stripe-Account' => $settings->stripe_account_id,
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.stripe.com/v1/refunds', [
                    'payment_intent' => $order->payment_intent_id,
                    'amount' => (int) $refund->amount_cents,
                    'metadata[tenant_id]' => $settings->tenant_id,
                    'metadata[order_id]' => $order->id,
                    'metadata[local_refund_id]' => $refund->id,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            throw new PaymentProviderException('The payment provider could not complete the refund request.');
        }

        if (! is_array($response)
            || ! is_string($response['id'] ?? null)
            || ! is_string($response['status'] ?? null)) {
            throw new PaymentProviderException('The payment provider returned an invalid refund response.');
        }

        return new PaymentRefundResult($response['id'], $response['status']);
    }

    public function cancelIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentCancellationResult {
        $secret = (string) config('services.stripe.secret');
        if (! str_starts_with($secret, 'sk_')
            || ! is_string($settings->stripe_account_id)
            || $settings->stripe_account_id === ''
            || ! is_string($order->payment_intent_id)
            || $order->payment_intent_id === '') {
            throw new PaymentProviderException('Stripe payment cancellation is not configured for this order.');
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->withToken($secret)
                ->withHeaders([
                    'Stripe-Account' => $settings->stripe_account_id,
                    'Idempotency-Key' => $idempotencyKey,
                ])
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.stripe.com/v1/payment_intents/'.rawurlencode($order->payment_intent_id).'/cancel')
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException) {
            throw new PaymentProviderException('The payment provider could not cancel the payment.');
        }

        if (! is_array($response) || ! is_string($response['status'] ?? null)) {
            throw new PaymentProviderException('The payment provider returned an invalid cancellation response.');
        }

        if ($response['status'] !== 'canceled') {
            throw new PaymentProviderException('The payment is no longer cancellable.');
        }

        return new PaymentCancellationResult($response['status']);
    }
}
