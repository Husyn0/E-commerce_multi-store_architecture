<?php

namespace App\Support;

use RuntimeException;

class VerificationCodeHasher
{
    public function hash(string $tenantId, string $identifier, string $purpose, string $code): string
    {
        $pepper = (string) config('services.verification_code_pepper');
        if (strlen($pepper) < 32) {
            throw new RuntimeException('VERIFICATION_CODE_PEPPER must contain at least 32 characters.');
        }

        return hash_hmac('sha256', implode("\0", [$tenantId, $identifier, $purpose, $code]), $pepper, true);
    }
}
