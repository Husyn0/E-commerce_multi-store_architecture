<?php

namespace App\Support;

use App\Models\User;
use App\Tenancy\TenantContext;
use JsonException;
use RuntimeException;

class TenantAccessToken
{
    public function issue(User $user, TenantContext $context): string
    {
        $now = time();
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'tid' => $context->tenant->id,
            'role' => $user->role,
            'iat' => $now,
            'exp' => $now + (int) config('auth.access_token_ttl', 5400),
        ];
        $header = $this->encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $body = $this->encode($payload);
        $signature = $this->base64UrlEncode(hash_hmac(
            'sha256',
            $header.'.'.$body,
            $this->signingKey(),
            true,
        ));

        return $header.'.'.$body.'.'.$signature;
    }

    /**
     * @return array{sub: string, tid: string, role: string}|null
     */
    public function verify(string $token, TenantContext $context): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header, $body, $signature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac(
            'sha256',
            $header.'.'.$body,
            $this->signingKey(),
            true,
        ));

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        try {
            $headerData = json_decode($this->base64UrlDecode($header), true, flags: JSON_THROW_ON_ERROR);
            $claims = json_decode($this->base64UrlDecode($body), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException|RuntimeException) {
            return null;
        }

        if (! is_array($headerData) || ($headerData['alg'] ?? null) !== 'HS256'
            || ! is_array($claims)
            || ($claims['iss'] ?? null) !== config('app.url')
            || ! is_string($claims['sub'] ?? null)
            || ($claims['tid'] ?? null) !== $context->tenant->id
            || ($claims['role'] ?? null) !== 'admin'
            || ! is_int($claims['exp'] ?? null)
            || $claims['exp'] <= time()) {
            return null;
        }

        return [
            'sub' => $claims['sub'],
            'tid' => $claims['tid'],
            'role' => $claims['role'],
        ];
    }

    private function encode(array $value): string
    {
        try {
            return $this->base64UrlEncode(json_encode($value, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode access token.', previous: $exception);
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid token encoding.');
        }

        return $decoded;
    }

    private function signingKey(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('APP_KEY is invalid.');
            }

            return $decoded;
        }

        if ($key === '') {
            throw new RuntimeException('APP_KEY is not configured.');
        }

        return $key;
    }
}
