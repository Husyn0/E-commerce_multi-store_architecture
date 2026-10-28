<?php

namespace App\Services\Payment;

use App\Models\TenantSettings;
use RuntimeException;

class PaymentGatewayManager
{
    public function forTenant(TenantSettings $settings): PaymentGateway
    {
        return match ($settings->payment_driver) {
            'demo' => $this->demoGateway($settings),
            'stripe' => $this->stripeGateway($settings),
            default => throw new RuntimeException('The tenant payment driver is not supported.'),
        };
    }

    private function demoGateway(TenantSettings $settings): PaymentGateway
    {
        if (app()->isProduction() || strlen((string) config('services.demo_webhook_secret')) < 32) {
            throw new PaymentProviderException('Demo payment processing is not configured.');
        }

        return app(DemoPaymentGateway::class);
    }

    private function stripeGateway(TenantSettings $settings): PaymentGateway
    {
        if (! str_starts_with((string) config('services.stripe.secret'), 'sk_')
            || (string) config('services.stripe.webhook_secret') === ''
            || ! is_string($settings->stripe_account_id)
            || $settings->stripe_account_id === '') {
            throw new PaymentProviderException('Stripe payment processing is not configured.');
        }

        return app(StripePaymentGateway::class);
    }
}
