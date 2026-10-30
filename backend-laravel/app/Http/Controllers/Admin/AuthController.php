<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\User;
use App\Models\UserSession;
use App\Support\SessionTokenHasher;
use App\Support\TenantAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends TenantApiController
{
    public function login(
        Request $request,
        TenantAccessToken $accessTokens,
        SessionTokenHasher $tokenHasher,
    ): JsonResponse {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (strlen((string) config('services.session_token_pepper')) < 32) {
            return response()->json(['message' => 'Authentication is temporarily unavailable.'], 503);
        }

        $user = User::query()
            ->where('tenant_id', $this->tenantId())
            ->where('email', Str::lower($credentials['email']))
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password_hash)) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 422);
        }

        $refreshToken = bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $tokenHasher, $user, $refreshToken): void {
            $user->last_login_at = now();
            $user->save();
            $this->createRefreshSession($user, $request, $tokenHasher, $refreshToken);
        });

        $csrfToken = Str::random(48);

        return response()->json([
            'access_token' => $accessTokens->issue($user, $this->tenantContext()),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('auth.access_token_ttl', 5400),
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'role' => $user->role,
            ],
        ])
            ->header('Cache-Control', 'no-store')
            ->withCookie(cookie('admin_refresh', $refreshToken, 60 * 24 * 30, '/', null, app()->isProduction(), true, false, 'lax'))
            ->withCookie(cookie('admin_csrf', $csrfToken, 60 * 24 * 30, '/', null, app()->isProduction(), false, false, 'lax'));
    }

    public function refresh(
        Request $request,
        TenantAccessToken $accessTokens,
        SessionTokenHasher $tokenHasher,
    ): JsonResponse {
        if (! $this->hasValidCsrfPair($request)) {
            return response()->json(['message' => 'CSRF validation failed.'], 419);
        }

        if (strlen((string) config('services.session_token_pepper')) < 32) {
            return response()->json(['message' => 'Authentication is temporarily unavailable.'], 503);
        }

        $rawToken = $request->cookie('admin_refresh');
        if (! is_string($rawToken) || $rawToken === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $result = DB::transaction(function () use ($request, $rawToken, $tokenHasher): ?array {
            $session = UserSession::query()
                ->where('tenant_id', $this->tenantId())
                ->where('session_type', 'admin_refresh')
                ->where('token_hash', $tokenHasher->hash($rawToken))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                return null;
            }

            $user = User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($session->user_id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->first();
            if ($user === null) {
                return null;
            }

            $session->revoked_at = now();
            $session->save();

            $newRefreshToken = bin2hex(random_bytes(32));
            $this->createRefreshSession($user, $request, $tokenHasher, $newRefreshToken);

            return [$user, $newRefreshToken];
        });

        if ($result === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401)
                ->withCookie(cookie()->forget('admin_refresh'))
                ->withCookie(cookie()->forget('admin_csrf'));
        }

        [$user, $newRefreshToken] = $result;
        $csrfToken = Str::random(48);

        return response()->json([
            'access_token' => $accessTokens->issue($user, $this->tenantContext()),
            'token_type' => 'Bearer',
            'expires_in' => (int) config('auth.access_token_ttl', 5400),
        ])
            ->header('Cache-Control', 'no-store')
            ->withCookie(cookie('admin_refresh', $newRefreshToken, 60 * 24 * 30, '/', null, app()->isProduction(), true, false, 'lax'))
            ->withCookie(cookie('admin_csrf', $csrfToken, 60 * 24 * 30, '/', null, app()->isProduction(), false, false, 'lax'));
    }

    public function logout(Request $request, SessionTokenHasher $tokenHasher): JsonResponse
    {
        if (! $this->hasValidCsrfPair($request)) {
            return response()->json(['message' => 'CSRF validation failed.'], 419);
        }

        $rawToken = $request->cookie('admin_refresh');
        if (is_string($rawToken) && $rawToken !== '' && strlen((string) config('services.session_token_pepper')) >= 32) {
            UserSession::query()
                ->where('tenant_id', $this->tenantId())
                ->where('session_type', 'admin_refresh')
                ->where('token_hash', $tokenHasher->hash($rawToken))
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        }

        return response()->json(['status' => 'ok'])
            ->header('Cache-Control', 'no-store')
            ->withCookie(cookie()->forget('admin_refresh'))
            ->withCookie(cookie()->forget('admin_csrf'));
    }

    private function createRefreshSession(
        User $user,
        Request $request,
        SessionTokenHasher $tokenHasher,
        string $rawToken,
    ): void {
        $session = new UserSession;
        $session->tenant_id = $user->tenant_id;
        $session->user_id = $user->id;
        $session->session_type = 'admin_refresh';
        $session->token_hash = $tokenHasher->hash($rawToken);
        $session->user_agent = Str::limit((string) $request->userAgent(), 512, '');
        $session->ip_address = $request->ip();
        $session->expires_at = now()->addDays(30);
        $session->last_used_at = now();
        $session->save();
    }

    private function hasValidCsrfPair(Request $request): bool
    {
        $cookie = $request->cookie('admin_csrf');
        $header = $request->header('X-CSRF-TOKEN');

        return is_string($cookie)
            && is_string($header)
            && $cookie !== ''
            && hash_equals($cookie, $header);
    }
}
