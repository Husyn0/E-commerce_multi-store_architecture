<?php

namespace App\Services\Storefront;

use RuntimeException;

class StorefrontCsrfToken
{
    public function issue(): string
    {
        $nonce = bin2hex(random_bytes(32));

        return $nonce.'.'.$this->signature($nonce);
    }

    public function isValid(?string $token): bool
    {
        if (! is_string($token)) {
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2 || strlen($parts[0]) !== 64 || strlen($parts[1]) !== 64) {
            return false;
        }

        return hash_equals($this->signature($parts[0]), $parts[1]);
    }

    private function signature(string $nonce): string
    {
        $pepper = (string) config('services.session_token_pepper');
        if (strlen($pepper) < 32) {
            throw new RuntimeException('SESSION_TOKEN_PEPPER must contain at least 32 characters.');
        }

        return hash_hmac('sha256', 'storefront-csrf:'.$nonce, $pepper);
    }
}
