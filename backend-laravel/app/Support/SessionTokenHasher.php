<?php

namespace App\Support;

use RuntimeException;

class SessionTokenHasher
{
    public function hash(string $token): string
    {
        $pepper = (string) config('services.session_token_pepper');
        if (strlen($pepper) < 32) {
            throw new RuntimeException('SESSION_TOKEN_PEPPER must contain at least 32 characters.');
        }

        return hash_hmac('sha256', $token, $pepper, true);
    }
}
