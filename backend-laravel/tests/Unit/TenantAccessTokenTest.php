<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use App\Support\SessionTokenHasher;
use App\Support\TenantAccessToken;
use App\Tenancy\TenantContext;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TenantAccessTokenTest extends TestCase
{
    #[Test]
    public function valid_token_contains_admin_identity_bound_to_resolved_tenant(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'app.url' => 'https://api.example.test',
            'auth.access_token_ttl' => 600,
        ]);
        $context = new TenantContext($this->tenant('tenant-a'), 'admin');
        $user = new User;
        $user->id = 'user-a';
        $user->role = 'admin';

        $service = new TenantAccessToken;
        $token = $service->issue($user, $context);

        $this->assertSame(
            ['sub' => 'user-a', 'tid' => 'tenant-a', 'role' => 'admin'],
            $service->verify($token, $context),
        );
        $this->assertNull($service->verify($token, new TenantContext($this->tenant('tenant-b'), 'admin')));
    }

    #[Test]
    public function altered_or_expired_token_is_rejected(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'app.url' => 'https://api.example.test',
            'auth.access_token_ttl' => 0,
        ]);
        $context = new TenantContext($this->tenant('tenant-a'), 'admin');
        $user = new User;
        $user->id = 'user-a';
        $user->role = 'admin';

        $service = new TenantAccessToken;
        $token = $service->issue($user, $context);

        $this->assertNull($service->verify($token, $context));
        config(['auth.access_token_ttl' => 600]);
        $validToken = $service->issue($user, $context);
        $this->assertNull($service->verify($validToken.'x', $context));
    }

    #[Test]
    public function session_token_hasher_requires_configured_pepper_and_returns_binary_digest(): void
    {
        $hasher = new SessionTokenHasher;
        config(['services.session_token_pepper' => '']);

        $this->expectException(RuntimeException::class);
        $hasher->hash('refresh-token');
    }

    #[Test]
    public function session_token_hasher_produces_a_fixed_length_hmac(): void
    {
        config(['services.session_token_pepper' => str_repeat('p', 48)]);

        $this->assertSame(32, strlen((new SessionTokenHasher)->hash('refresh-token')));
    }

    private function tenant(string $tenantId): Tenant
    {
        $tenant = new Tenant;
        $tenant->id = $tenantId;

        return $tenant;
    }
}
