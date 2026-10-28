<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\TenantSettings;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GuestOrderLookupController extends TenantApiController
{
    public function requestCode(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:254'],
        ]);
        $tenantId = $this->tenantId();
        $orderNumber = strtoupper(trim($input['order_number']));
        $email = Str::lower(trim($input['email']));

        $orderExists = Order::query()
            ->forTenant($tenantId)
            ->whereNull('user_id')
            ->where('order_number', $orderNumber)
            ->where('customer_email', $email)
            ->exists();

        if ($orderExists) {
            $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
            try {
                $verificationCodes->issueEmail($settings, $email, 'guest_order_lookup', null, $request->ip());
            } catch (RuntimeException $exception) {
                report($exception);
            }
        }

        return response()->json([
            'message' => 'If the order details match, a verification code will be sent to the order email.',
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function lookup(Request $request, VerificationCodeService $verificationCodes): JsonResponse
    {
        $input = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:254'],
            'code' => ['required', 'digits:6'],
        ]);
        $tenantId = $this->tenantId();
        $orderNumber = strtoupper(trim($input['order_number']));
        $email = Str::lower(trim($input['email']));
        $order = Order::query()
            ->forTenant($tenantId)
            ->whereNull('user_id')
            ->where('order_number', $orderNumber)
            ->where('customer_email', $email)
            ->with(['items', 'refunds'])
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->first();

        if ($order === null) {
            throw ValidationException::withMessages(['code' => 'The order details or verification code are invalid.']);
        }

        try {
            $valid = $verificationCodes->consumeEmail($tenantId, $email, 'guest_order_lookup', $input['code']);
        } catch (RuntimeException $exception) {
            report($exception);
            abort(503, 'Order lookup is temporarily unavailable.');
        }
        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The order details or verification code are invalid.']);
        }

        return (new OrderResource($order))
            ->additional(['meta' => ['tenant_scoped' => true]])
            ->response()
            ->header('Cache-Control', 'no-store');
    }
}
