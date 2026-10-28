<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveTenant
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenantManager->requireStatus() === 'suspended') {
            if ($this->tenantManager->current()?->hostType === 'admin') {
                return response()->json([
                    'code' => 'tenant_suspended',
                    'message' => 'Tenant access is suspended.',
                ], 403)->header('Cache-Control', 'no-store');
            }

            return response()->json([
                'code' => 'tenant_suspended',
                'message' => 'Storefront temporarily unavailable.',
            ], 503)->header('Cache-Control', 'no-store')
                ->header('Retry-After', '60');
        }

        return $next($request);
    }
}
