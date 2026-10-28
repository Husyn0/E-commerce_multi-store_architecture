<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserSession;
use App\Support\SessionTokenHasher;
use App\Tenancy\TenantManager;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStorefrontSession
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly SessionTokenHasher $tokenHasher,
    ) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $context = $this->tenantManager->current();
        $token = $request->cookie('storefront_session');
        $optional = $mode === 'optional';
        if ($context === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! is_string($token) || strlen($token) !== 64 || ! ctype_xdigit($token)) {
            return $optional ? $next($request) : response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $tokenHash = $this->tokenHasher->hash($token);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Authentication is temporarily unavailable.'], 503);
        }

        $session = UserSession::query()
            ->forTenant($context->tenant->id)
            ->where('session_type', 'storefront')
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();
        $user = $session?->user;
        if ($session === null || ! $user instanceof User
            || $user->role !== 'customer'
            || $user->status !== 'active') {
            return $optional ? $next($request) : response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $idleTtl = max(60, (int) config('services.session_idle_ttl', 1209600));
        if ($session->last_used_at !== null && $session->last_used_at->lt(now()->subSeconds($idleTtl))) {
            $session->revoked_at = now();
            $session->save();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $session->last_used_at = now();
        $session->save();
        $request->setUserResolver(fn (): User => $user);
        $request->attributes->set('storefront_user_session', $session);

        return $next($request);
    }
}
