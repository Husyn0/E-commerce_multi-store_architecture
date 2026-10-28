<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantHostType
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next, string $expectedType): Response
    {
        if ($this->tenantManager->current()?->hostType !== $expectedType) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        return $next($request);
    }
}
