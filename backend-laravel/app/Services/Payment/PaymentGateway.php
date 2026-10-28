<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Models\Refund;
use App\Models\TenantSettings;

interface PaymentGateway
{
    public function createIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentIntentResult;

    public function createRefund(
        TenantSettings $settings,
        Order $order,
        Refund $refund,
        string $idempotencyKey,
    ): PaymentRefundResult;

    public function cancelIntent(
        TenantSettings $settings,
        Order $order,
        string $idempotencyKey,
    ): PaymentCancellationResult;
}
