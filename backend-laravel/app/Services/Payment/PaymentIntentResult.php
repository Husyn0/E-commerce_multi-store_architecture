<?php

namespace App\Services\Payment;

final readonly class PaymentIntentResult
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public string $status,
    ) {}
}
