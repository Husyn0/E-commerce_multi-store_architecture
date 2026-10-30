<?php

namespace Tests\Feature;

use App\Http\Middleware\RequireActiveTenant;
use App\Http\Middleware\RequireTenantHostType;
use App\Http\Middleware\ResolveTenantFromHost;
use App\Models\Tenant;
use App\Tenancy\TenantHostLocator;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class TenantHostResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.admin_base_domain' => 'admin.example.test',
            'tenancy.storefront_base_domain' => 'store.example.test',
            'tenancy.custom_domain_allowlist' => [],
        ]);

        Route::get('/__test/admin-protected', fn () => response()->noContent())
            ->middleware(['tenant.resolve', 'tenant.host:admin', 'tenant.active', 'auth']);
    }

    public function test_admin_subdomain_resolves_tenant_and_host_type(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet')]);

        $this->getJson('https://violet.admin.example.test/api/admin/tenant')
            ->assertOk()
            ->assertJsonPath('id', 'tenant-1')
            ->assertJsonPath('host_type', 'admin');
    }

    public function test_storefront_subdomain_resolves_tenant(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet')]);

        $this->getJson('https://violet.store.example.test/api/storefront/tenant')
            ->assertOk()
            ->assertJsonPath('host_type', 'storefront');
    }

    public function test_unknown_hosts_do_not_resolve_a_tenant(): void
    {
        $this->bindTenants([]);

        $this->getJson('https://unknown.example.test/api/storefront/tenant')
            ->assertNotFound();
    }

    public function test_custom_domain_requires_allowlist_and_tenant_match(): void
    {
        $tenant = $this->tenant('violet', 'shop.example.com');
        $this->bindTenants([], ['shop.example.com' => $tenant]);

        $this->getJson('https://shop.example.com/api/storefront/tenant')
            ->assertNotFound();

        config(['tenancy.custom_domain_allowlist' => ['shop.example.com']]);

        $this->getJson('https://shop.example.com/api/storefront/tenant')
            ->assertOk()
            ->assertJsonPath('host_type', 'storefront');
    }

    public function test_storefront_routes_reject_admin_hosts(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet')]);

        $this->getJson('https://violet.admin.example.test/api/storefront/tenant')
            ->assertNotFound();
    }

    public function test_tenant_resolution_and_gates_precede_jwt_authentication(): void
    {
        $this->app->make(Kernel::class);
        $router = $this->app->make(Router::class);
        $route = $router->getRoutes()->getByName('products.index');

        $this->assertNotNull($route);

        $middleware = $router->gatherRouteMiddleware($route);
        $tenantResolution = array_search(ResolveTenantFromHost::class, $middleware, true);
        $adminHost = array_search(RequireTenantHostType::class.':admin', $middleware, true);
        $activeTenant = array_search(RequireActiveTenant::class, $middleware, true);
        $authentication = array_search(Authenticate::class.':tenant-jwt', $middleware, true);

        $this->assertIsInt($tenantResolution);
        $this->assertIsInt($adminHost);
        $this->assertIsInt($activeTenant);
        $this->assertIsInt($authentication);
        $this->assertLessThan($authentication, $tenantResolution);
        $this->assertLessThan($authentication, $adminHost);
        $this->assertLessThan($authentication, $activeTenant);
    }

    public function test_unauthenticated_admin_api_request_without_accept_header_returns_json_401(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet')]);

        $this->call('GET', 'https://violet.admin.example.test/api/admin/products')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_suspended_admin_is_blocked_but_can_read_recovery_status(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet', status: 'suspended')]);

        $this->getJson('https://violet.admin.example.test/__test/admin-protected')
            ->assertForbidden()
            ->assertJsonPath('code', 'tenant_suspended');

        $this->getJson('https://violet.admin.example.test/api/admin/tenant')
            ->assertOk()
            ->assertJsonPath('status', 'suspended');
    }

    public function test_suspended_storefront_returns_uncached_service_unavailable(): void
    {
        $this->bindTenants(['violet' => $this->tenant('violet', status: 'suspended')]);

        $response = $this->getJson('https://violet.store.example.test/api/storefront/tenant');

        $response->assertStatus(503)->assertHeader('Retry-After', '60');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_readiness_endpoint_checks_database_without_a_tenant_host(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')->andReturn([]);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_readiness_endpoint_reports_database_failure_as_unavailable(): void
    {
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new PDOException('database unavailable'));

        $response = $this->getJson('/health/ready');

        $response
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    /**
     * @param  array<string, Tenant>  $subdomains
     * @param  array<string, Tenant>  $customDomains
     */
    private function bindTenants(array $subdomains, array $customDomains = []): void
    {
        $this->app->instance(TenantHostLocator::class, new class($subdomains, $customDomains) implements TenantHostLocator
        {
            /**
             * @param  array<string, Tenant>  $subdomains
             * @param  array<string, Tenant>  $customDomains
             */
            public function __construct(
                private readonly array $subdomains,
                private readonly array $customDomains,
            ) {}

            public function findBySubdomain(string $subdomain): ?Tenant
            {
                return $this->subdomains[$subdomain] ?? null;
            }

            public function findByCustomDomain(string $domain): ?Tenant
            {
                return $this->customDomains[$domain] ?? null;
            }
        });
    }

    private function tenant(string $subdomain, ?string $customDomain = null, string $status = 'active'): Tenant
    {
        $tenant = new Tenant;
        $tenant->setRawAttributes([
            'id' => 'tenant-1',
            'name' => 'Violet',
            'subdomain' => $subdomain,
            'custom_domain' => $customDomain,
            'status' => $status,
            'default_currency' => 'USD',
            'default_locale' => 'en',
        ]);

        return $tenant;
    }
}
