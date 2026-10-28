<?php

namespace App\Services\Payment;

final readonly class PaymentRefundResult
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}
}
