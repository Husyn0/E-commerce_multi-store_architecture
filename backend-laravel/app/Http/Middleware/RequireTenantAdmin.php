<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantAdmin
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $context = $this->tenantManager->current();

        if ($user === null
            || $context === null
            || $user->role !== 'admin'
            || $user->tenant_id !== $context->tenant->id
            || $user->status !== 'active') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
