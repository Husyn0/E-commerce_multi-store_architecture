<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Verification\VerificationCodeService;
use App\Support\PhoneNumber;
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
        $input = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:254'],
            'phone' => ['required_if:channel,sms', 'nullable', 'string', 'max:32'],
        ]);
        $tenantId = $this->tenantId();
        $channel = $input['channel'];
        $identifier = $channel === 'email'
            ? Str::lower(trim($input['email']))
            : PhoneNumber::normalize($input['phone']);
        $userQuery = User::query()
            ->where('tenant_id', $tenantId)
            ->where('role', 'customer')
            ->where('status', 'active');
        $userQuery->where($channel === 'email' ? 'email' : 'phone', $identifier);
        $users = $channel === 'sms' ? $userQuery->limit(2)->get() : collect([$userQuery->first()])->filter();
        $user = $users->count() === 1 ? $users->first() : null;

        if ($user !== null) {
            $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
            try {
                if ($channel === 'email') {
                    $verificationCodes->issueEmail($settings, $identifier, 'password_reset', $user->id, $request->ip());
                } else {
                    $verificationCodes->issueSms($settings, $identifier, 'password_reset', $user->id, $request->ip());
                }
            } catch (RuntimeException $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => 'If an eligible customer account exists, a reset code will be sent.',
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function reset(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate([
            'channel' => ['required', 'in:email,sms'],
            'email' => ['required_if:channel,email', 'nullable', 'email', 'max:254'],
            'phone' => ['required_if:channel,sms', 'nullable', 'string', 'max:32'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);
        $tenantId = $this->tenantId();
        $channel = $input['channel'];
        $identifier = $channel === 'email'
            ? Str::lower(trim($input['email']))
            : PhoneNumber::normalize($input['phone']);
        $userQuery = User::query()
            ->where('tenant_id', $tenantId)
            ->where($channel === 'email' ? 'email' : 'phone', $identifier)
            ->where('role', 'customer')
            ->where('status', 'active');
        if ($channel === 'sms') {
            $matches = $userQuery->limit(2)->get();
            $user = $matches->count() === 1 ? $matches->first() : null;
        } else {
            $user = $userQuery->first();
        }
        if ($user === null) {
            throw ValidationException::withMessages(['code' => 'The reset code is invalid or expired.']);
        }

        try {
            $valid = $channel === 'email'
                ? $verificationCodes->consumeEmail($tenantId, $identifier, 'password_reset', $input['code'])
                : $verificationCodes->consumeSms($tenantId, $identifier, 'password_reset', $input['code']);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Password reset is temporarily unavailable.'], 503);
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The reset code is invalid or expired.']);
        }

        DB::transaction(function () use ($input, $tenantId, $user, $channel): void {
            $lockedUser = User::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser->password_hash = Hash::make($input['password']);
            if ($channel === 'sms') {
                $lockedUser->phone_verified_at ??= now();
            }
            $lockedUser->save();
            UserSession::query()
                ->forTenant($tenantId)
                ->where('user_id', $lockedUser->id)
                ->where('session_type', 'storefront')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
        });

        return response()->json(['password_reset' => true])->header('Cache-Control', 'no-store');
    }
}
