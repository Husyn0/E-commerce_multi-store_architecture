<?php

namespace App\Http\Middleware;

use App\Models\HumanVerification;
use App\Models\TenantSettings;
use App\Support\SessionTokenHasher;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EnforceConfiguredVerification
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly SessionTokenHasher $tokenHasher,
    ) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        $context = $this->tenantManager->current();
        $sessionToken = $request->cookie('storefront_session') ?? $request->cookie('storefront_cart');
        if ($context === null || ! is_string($sessionToken) || strlen($sessionToken) !== 64 || ! ctype_xdigit($sessionToken)) {
            return response()->json(['message' => 'Human verification is required.'], 422);
        }

        try {
            $tokenHash = $this->tokenHasher->hash($sessionToken);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Human verification is temporarily unavailable.'], 503);
        }

        try {
            $settings = TenantSettings::query()->forTenant($context->tenant->id)->first();
            if ($settings === null) {
                return response()->json(['message' => 'Human verification is not configured.'], 503);
            }

            $verification = HumanVerification::query()
                ->forTenant($context->tenant->id)
                ->where('session_token_hash', $tokenHash)
                ->where('action', $action)
                ->where('passed', true)
                ->where('expires_at', '>', now())
                ->first();
        } catch (QueryException $exception) {
            report($exception);

            return response()->json(['message' => 'Human verification is temporarily unavailable.'], 503);
        }

        if ($verification === null
            || $verification->provider !== $settings->human_check_driver
            || ($verification->ip_address !== null && $verification->ip_address !== $request->ip())) {
            return response()->json(['message' => 'Complete human verification before continuing.'], 422);
        }

        return $next($request);
    }
}
