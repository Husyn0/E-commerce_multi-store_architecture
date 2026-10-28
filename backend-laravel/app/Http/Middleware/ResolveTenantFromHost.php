<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantHostLocator;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantFromHost
{
    public function __construct(
        private readonly TenantHostLocator $tenantLocator,
        private readonly TenantManager $tenantManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = rtrim(Str::lower($request->getHost()), '.');
        $adminSubdomain = $this->subdomainFor($host, config('tenancy.admin_base_domain'));
        $storefrontSubdomain = $this->subdomainFor($host, config('tenancy.storefront_base_domain'));

        if ($adminSubdomain !== null) {
            $tenant = $this->tenantLocator->findBySubdomain($adminSubdomain);
            $hostType = 'admin';
        } elseif ($storefrontSubdomain !== null) {
            $tenant = $this->tenantLocator->findBySubdomain($storefrontSubdomain);
            $hostType = 'storefront';
        } elseif (in_array($host, config('tenancy.custom_domain_allowlist', []), true)) {
            $tenant = $this->tenantLocator->findByCustomDomain($host);
            $hostType = 'storefront';
        } else {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        if ($tenant === null) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $context = new TenantContext($tenant, $hostType);
        $this->tenantManager->set($context);
        $request->attributes->set(TenantContext::class, $context);

        return $next($request);
    }

    private function subdomainFor(string $host, ?string $baseDomain): ?string
    {
        if ($baseDomain === null || $baseDomain === '') {
            return null;
        }

        $suffix = '.'.rtrim(Str::lower($baseDomain), '.');
        if (! Str::endsWith($host, $suffix)) {
            return null;
        }

        $subdomain = Str::beforeLast($host, $suffix);

        return preg_match('/^(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain) === 1
            ? $subdomain
            : null;
    }
}
