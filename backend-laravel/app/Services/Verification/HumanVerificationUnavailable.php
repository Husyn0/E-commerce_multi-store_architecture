<?php

namespace App\Services\Verification;

use RuntimeException;

class HumanVerificationUnavailable extends RuntimeException
{
    public static function forProvider(): self
    {
        return new self('Human verification provider is not configured or unavailable.');
    }
}
