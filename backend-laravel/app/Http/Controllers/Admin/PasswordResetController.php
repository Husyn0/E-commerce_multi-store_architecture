<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PasswordResetController extends TenantApiController
{
    public function requestCode(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $tenantId = $this->tenantId();
        $email = Str::lower(trim($input['email']));
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if ($user !== null) {
            $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
            try {
                $issued = $verificationCodes->issueEmail(
                    $settings,
                    $email,
                    'password_reset',
                    $user->id,
                    $request->ip(),
                );
            } catch (RuntimeException $exception) {
                report($exception);

                return response()->json(['message' => 'Password reset is temporarily unavailable.'], 503);
            }
            if (! $issued) {
                return response()->json(['message' => 'Please wait before requesting another reset code.'], 429);
            }
        }

        return response()->json([
            'message' => 'If an eligible Admin account exists, a reset code will be sent.',
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);
        $tenantId = $this->tenantId();
        $email = Str::lower(trim($input['email']));
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();
        if ($user === null) {
            throw ValidationException::withMessages(['code' => 'The reset code is invalid or expired.']);
        }

        try {
            $valid = $verificationCodes->consumeEmail($tenantId, $email, 'password_reset', $input['code']);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Password reset is temporarily unavailable.'], 503);
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The reset code is invalid or expired.']);
        }

        DB::transaction(function () use ($input, $tenantId, $user): void {
            $lockedUser = User::query()->where('tenant_id', $tenantId)->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->password_hash = Hash::make($input['password']);
            $lockedUser->save();
            UserSession::query()
                ->forTenant($tenantId)
                ->where('user_id', $lockedUser->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });

        return response()->json(['password_reset' => true])->header('Cache-Control', 'no-store');
    }
}
