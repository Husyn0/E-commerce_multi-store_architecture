<?php

namespace App\Services\Payment;

final readonly class PaymentCancellationResult
{
    public function __construct(public string $status) {}
}
