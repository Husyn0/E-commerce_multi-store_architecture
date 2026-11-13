<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class TenantReadCache
{
    private const TTL_SECONDS = 60;

    public function remember(
        string $tenantId,
        string $host,
        string $dataset,
        array $parameters,
        Closure $callback,
    ): mixed {
        ksort($parameters);
        $generationKey = $this->generationKey($tenantId);
        $missing = new \stdClass;

        try {
            $generation = Cache::get($generationKey, '0');
        } catch (Throwable $exception) {
            report($exception);
            return $callback();
        }

        $fingerprint = hash('sha256', serialize([$host, $parameters]));
        $key = "tenant-read:v1:{$tenantId}:{$generation}:{$dataset}:{$fingerprint}";

        try {
            $cached = Cache::get($key, $missing);
        } catch (Throwable $exception) {
            report($exception);
            return $callback();
        }
        if ($cached !== $missing) {
            return $cached;
        }

        $value = $callback();
        try {
            Cache::put($key, $value, now()->addSeconds(self::TTL_SECONDS));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $value;
    }

    public function invalidateTenant(string $tenantId): void
    {
        try {
            Cache::forever($this->generationKey($tenantId), (string) Str::uuid());
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function generationKey(string $tenantId): string
    {
        return "tenant-read:v1:{$tenantId}:generation";
    }
}
