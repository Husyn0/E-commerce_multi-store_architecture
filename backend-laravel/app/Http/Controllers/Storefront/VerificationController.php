<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Services\Verification\HumanVerificationService;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class VerificationController extends TenantApiController
{
    public function verifyHuman(Request $request, HumanVerificationService $humanVerification): JsonResponse
    {
        $input = $request->validate([
            'action' => ['required', 'in:register,checkout'],
            'response_token' => ['required', 'string', 'max:4096'],
        ]);
        $settings = TenantSettings::query()->forTenant($this->tenantId())->firstOrFail();

        try {
            $humanVerification->verify($request, $settings, $input['action'], $input['response_token']);
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Human verification is temporarily unavailable.'], 503);
        }

        return response()->json(['verified' => true], 200)->header('Cache-Control', 'no-store');
    }

    public function issueEmail(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
        ]);
        $settings = TenantSettings::query()->forTenant($this->tenantId())->firstOrFail();

        try {
            $issued = $verificationCodes->issueEmail(
                $settings,
                $input['email'],
                'verify_email',
                null,
                $request->ip(),
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Email verification is temporarily unavailable.'], 503);
        }

        if (! $issued) {
            return response()->json(['message' => 'Please wait before requesting another verification code.'], 429);
        }

        return response()->json([
            'message' => 'If verification delivery is available, a code will be sent shortly.',
        ], 202)->header('Cache-Control', 'no-store');
    }
}
