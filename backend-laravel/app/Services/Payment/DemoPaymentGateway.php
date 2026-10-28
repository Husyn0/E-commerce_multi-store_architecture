<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Refund;
use App\Models\TenantSettings;

class DemoPaymentGateway implements PaymentGateway
{
    public function createIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentIntentResult {
        $reference = substr(hash('sha256', $settings->tenant_id.'|'.$idempotencyKey), 0, 32);

        return new PaymentIntentResult(
            'demo_pi_'.$reference,
            'demo_secret_'.$reference,
            'requires_payment_method',
        );
    }

    public function createRefund(
        TenantSettings $settings,
        Order $order,
        Refund $refund,
        string $idempotencyKey,
    ): PaymentRefundResult {
        return new PaymentRefundResult(
            'demo_re_'.substr(hash('sha256', $settings->tenant_id.'|'.$idempotencyKey), 0, 32),
            'pending',
        );
    }

    public function cancelIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentCancellationResult {
        return new PaymentCancellationResult('canceled');
    }
}
