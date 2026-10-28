<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Storefront\StorefrontSessionCookies;
use App\Services\Verification\VerificationCodeService;
use App\Support\SessionTokenHasher;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AuthController extends TenantApiController
{
    public function register(
        Request $request,
        SessionTokenHasher $tokenHasher,
        VerificationCodeService $verificationCodes,
        StorefrontSessionCookies $cookies,
    ): JsonResponse {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email_verification_code' => ['sometimes', 'digits:6'],
        ]);
        $tenantId = $this->tenantId();
        $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
        if ($settings->require_phone_verification) {
            return response()->json(['message' => 'Phone verification is not configured for registration.'], 503);
        }

        $email = Str::lower(trim($input['email']));
        if ($settings->require_email_verification) {
            if (! isset($input['email_verification_code'])) {
                throw ValidationException::withMessages([
                    'email_verification_code' => 'A valid email verification code is required.',
                ]);
            }
            try {
                $verified = $verificationCodes->consumeEmail(
                    $tenantId,
                    $email,
                    'verify_email',
                    $input['email_verification_code'],
                );
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json(['message' => 'Email verification is temporarily unavailable.'], 503);
            }
            if (! $verified) {
                throw ValidationException::withMessages([
                    'email_verification_code' => 'The verification code is invalid or expired.',
                ]);
            }
        }

        try {
            [$user, $token] = DB::transaction(function () use ($email, $input, $request, $settings, $tenantId, $tokenHasher): array {
                $user = new User;
                $user->tenant_id = $tenantId;
                $user->email = $email;
                $user->email_verified_at = $settings->require_email_verification ? now() : null;
                $user->password_hash = $input['password'];
                $user->first_name = $input['first_name'] ?? null;
                $user->last_name = $input['last_name'] ?? null;
                $user->role = 'customer';
                $user->status = 'active';
                $user->save();
                $token = $this->createSession($user, $request, $tokenHasher);

                return [$user, $token];
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062
                && User::query()->where('tenant_id', $tenantId)->where('email', $email)->exists()) {
                return response()->json(['message' => 'An account with that email already exists.'], 409);
            }

            throw $exception;
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Authentication is temporarily unavailable.'], 503);
        }

        return $cookies->attach(
            response()->json(['user' => $this->userData($user)], 201),
            $request,
            $token,
        );
    }

    public function login(
        Request $request,
        SessionTokenHasher $tokenHasher,
        StorefrontSessionCookies $cookies,
    ): JsonResponse {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:255'],
        ]);
        $tenantId = $this->tenantId();
        $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', Str::lower(trim($credentials['email'])))
            ->where('role', 'customer')
            ->where('status', 'active')
            ->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password_hash)
            || ($settings->require_email_verification && $user->email_verified_at === null)) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 422);
        }

        try {
            $token = DB::transaction(function () use ($request, $tokenHasher, $user): string {
                $user->last_login_at = now();
                $user->save();

                return $this->createSession($user, $request, $tokenHasher);
            });
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Authentication is temporarily unavailable.'], 503);
        }

        return $cookies->attach(
            response()->json(['user' => $this->userData($user)]),
            $request,
            $token,
        );
    }

    public function logout(Request $request, StorefrontSessionCookies $cookies): JsonResponse
    {
        $session = $request->attributes->get('storefront_user_session');
        if ($session instanceof UserSession) {
            $session->revoked_at = now();
            $session->save();
        }

        return $cookies->clear(response()->json(['logged_out' => true]), $request);
    }

    private function createSession(User $user, Request $request, SessionTokenHasher $tokenHasher): string
    {
        $token = bin2hex(random_bytes(32));
        $session = new UserSession;
        $session->tenant_id = $user->tenant_id;
        $session->user_id = $user->id;
        $session->session_type = 'storefront';
        $session->token_hash = $tokenHasher->hash($token);
        $session->user_agent = mb_substr((string) $request->userAgent(), 0, 512);
        $session->ip_address = $request->ip();
        $session->expires_at = now()->addDays(30);
        $session->save();

        return $token;
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
