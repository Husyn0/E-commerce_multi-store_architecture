<?php

namespace App\Services\Payment;

class CheckoutCurrency
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public function supportsTwoMinorUnits(string $currency): bool
    {
        return preg_match('/^[A-Z]{3}$/D', $currency) === 1
            && ! in_array($currency, self::ZERO_DECIMAL, true);
    }
}
