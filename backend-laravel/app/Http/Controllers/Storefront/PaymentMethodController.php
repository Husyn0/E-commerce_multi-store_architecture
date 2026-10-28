<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\PaymentMethod;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\Payment\PaymentProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaymentMethodController extends TenantApiController
{
    public function configuration(): JsonResponse
    {
        $settings = TenantSettings::query()->forTenant($this->tenantId())->firstOrFail();
        $config = [
            'provider' => $settings->payment_driver,
            'publishable_key' => null,
            'connected_account_id' => null,
            'saved_methods_supported' => false,
        ];

        if ($settings->payment_driver === 'stripe') {
            $publishableKey = config('services.stripe.publishable');
            if (! is_string($publishableKey) || ! str_starts_with($publishableKey, 'pk_')
                || ! is_string($settings->stripe_account_id) || $settings->stripe_account_id === '') {
                return response()->json(['message' => 'Store payment configuration is unavailable.'], 503)
                    ->header('Cache-Control', 'no-store');
            }
            $config['publishable_key'] = $publishableKey;
            $config['connected_account_id'] = $settings->stripe_account_id;
            $config['saved_methods_supported'] = true;
        }

        return response()->json($config)->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $methods = PaymentMethod::query()
            ->forTenant($this->tenantId())
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PaymentMethod $method): array => $this->methodData($method));

        return response()->json(['data' => $methods])->header('Cache-Control', 'no-store');
    }

    public function setupIntent(Request $request): JsonResponse
    {
        $user = $this->customer($request);
        $settings = $this->stripeSettings();
        $secret = $this->stripeSecret();

        try {
            $customerId = PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->where('provider', 'stripe')
                ->value('provider_customer_id');

            if (! is_string($customerId) || $customerId === '') {
                $customer = Http::asForm()
                    ->acceptJson()
                    ->withToken($secret)
                    ->withHeaders([
                        'Stripe-Account' => $settings->stripe_account_id,
                        'Idempotency-Key' => 'storefront_customer_'.hash('sha256', $this->tenantId().'|'.$user->id),
                    ])
                    ->connectTimeout(3)
                    ->timeout(10)
                    ->post('https://api.stripe.com/v1/customers', [
                        'email' => $user->email,
                        'metadata[tenant_id]' => $this->tenantId(),
                        'metadata[user_id]' => $user->id,
                    ])
                    ->throw()
                    ->json();

                if (! is_array($customer)
                    || ! is_string($customer['id'] ?? null)
                    || ! str_starts_with($customer['id'], 'cus_')) {
                    throw new PaymentProviderException('The payment provider returned an invalid customer.');
                }
                $customerId = $customer['id'];
            }

            $intent = Http::asForm()
                ->acceptJson()
                ->withToken($secret)
                ->withHeaders(['Stripe-Account' => $settings->stripe_account_id])
                ->connectTimeout(3)
                ->timeout(10)
                ->post('https://api.stripe.com/v1/setup_intents', [
                    'customer' => $customerId,
                    'usage' => 'off_session',
                    'payment_method_types[]' => 'card',
                    'metadata[tenant_id]' => $this->tenantId(),
                    'metadata[user_id]' => $user->id,
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException|PaymentProviderException $exception) {
            report($exception);

            return response()->json(['message' => 'The payment provider could not start secure card setup.'], 502);
        }

        if (! is_array($intent)
            || ! is_string($intent['id'] ?? null)
            || ! is_string($intent['client_secret'] ?? null)) {
            report(new PaymentProviderException('The payment provider returned an invalid setup intent.'));

            return response()->json(['message' => 'The payment provider returned an invalid setup response.'], 502);
        }

        return response()->json([
            'setup_intent_id' => $intent['id'],
            'client_secret' => $intent['client_secret'],
            'publishable_key' => config('services.stripe.publishable'),
            'connected_account_id' => $settings->stripe_account_id,
        ])->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'setup_intent_id' => ['required', 'string', 'regex:/^seti_[A-Za-z0-9]+$/', 'max:100'],
        ]);
        $user = $this->customer($request);
        $settings = $this->stripeSettings();

        try {
            $intent = Http::acceptJson()
                ->withToken($this->stripeSecret())
                ->withHeaders(['Stripe-Account' => $settings->stripe_account_id])
                ->connectTimeout(3)
                ->timeout(10)
                ->get('https://api.stripe.com/v1/setup_intents/'.rawurlencode($input['setup_intent_id']), [
                    'expand[]' => 'payment_method',
                ])
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return response()->json(['message' => 'The payment provider could not verify card setup.'], 502);
        }

        if (! is_array($intent) || ! is_array($intent['metadata'] ?? null)) {
            report(new PaymentProviderException('The payment provider returned an invalid setup intent.'));

            return response()->json(['message' => 'The payment provider returned an invalid setup response.'], 502);
        }

        $metadata = $intent['metadata'];
        $paymentMethod = $intent['payment_method'] ?? null;
        $card = is_array($paymentMethod) ? ($paymentMethod['card'] ?? null) : null;
        $customerId = $intent['customer'] ?? null;
        if (($intent['status'] ?? null) !== 'succeeded'
            || ! is_string($customerId)
            || ($metadata['tenant_id'] ?? null) !== $this->tenantId()
            || ($metadata['user_id'] ?? null) !== $user->id
            || ! is_array($paymentMethod)
            || ! is_string($paymentMethod['id'] ?? null)
            || ($paymentMethod['customer'] ?? null) !== $customerId
            || ! is_array($card)
            || ! is_string($card['brand'] ?? null)
            || ! is_string($card['last4'] ?? null)) {
            throw ValidationException::withMessages([
                'setup_intent_id' => 'The completed card setup does not belong to this customer.',
            ]);
        }

        $method = DB::transaction(function () use ($card, $customerId, $paymentMethod, $user): PaymentMethod {
            $lockedUser = User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $lockedUser->id)
                ->where('provider', 'stripe')
                ->where('provider_payment_method_id', $paymentMethod['id'])
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $method = new PaymentMethod;
            $method->tenant_id = $this->tenantId();
            $method->user_id = $lockedUser->id;
            $method->provider = 'stripe';
            $method->provider_customer_id = $customerId;
            $method->provider_payment_method_id = $paymentMethod['id'];
            $method->card_brand = mb_substr($card['brand'], 0, 40);
            $method->card_last4 = preg_match('/^\d{4}$/', $card['last4']) === 1 ? $card['last4'] : null;
            $method->card_exp_month = filter_var($card['exp_month'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $method->card_exp_year = filter_var($card['exp_year'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $method->is_default = ! PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $lockedUser->id)
                ->where('provider', 'stripe')
                ->where('is_default', true)
                ->exists();
            $method->created_at = now();
            $method->save();

            return $method;
        });

        return response()->json(['data' => $this->methodData($method)], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function makeDefault(Request $request, string $paymentMethodId): JsonResponse
    {
        $user = $this->customer($request);

        DB::transaction(function () use ($paymentMethodId, $user): void {
            User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $method = PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->findOrFail($paymentMethodId);
            PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->where('provider', $method->provider)
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $method->is_default = true;
            $method->save();
        });

        return response()->json(['is_default' => true])->header('Cache-Control', 'no-store');
    }

    public function destroy(Request $request, string $paymentMethodId): JsonResponse
    {
        $user = $this->customer($request);
        $method = PaymentMethod::query()
            ->forTenant($this->tenantId())
            ->where('user_id', $user->id)
            ->findOrFail($paymentMethodId);

        if ($method->provider === 'stripe') {
            $settings = $this->stripeSettings();
            try {
                Http::asForm()
                    ->acceptJson()
                    ->withToken($this->stripeSecret())
                    ->withHeaders(['Stripe-Account' => $settings->stripe_account_id])
                    ->connectTimeout(3)
                    ->timeout(10)
                    ->post('https://api.stripe.com/v1/payment_methods/'
                        .rawurlencode($method->provider_payment_method_id).'/detach')
                    ->throw();
            } catch (ConnectionException|RequestException $exception) {
                report($exception);

                return response()->json(['message' => 'The payment provider could not remove this payment method.'], 502);
            }
        }

        DB::transaction(function () use ($method, $user): void {
            User::query()
                ->where('tenant_id', $this->tenantId())
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            PaymentMethod::query()
                ->forTenant($this->tenantId())
                ->where('user_id', $user->id)
                ->whereKey($method->id)
                ->delete();
        });

        return response()->json(['deleted' => true])->header('Cache-Control', 'no-store');
    }

    private function customer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === 'customer' && $user->tenant_id === $this->tenantId(), 401);

        return $user;
    }

    private function stripeSettings(): TenantSettings
    {
        $settings = TenantSettings::query()->forTenant($this->tenantId())->firstOrFail();
        if ($settings->payment_driver !== 'stripe'
            || ! is_string($settings->stripe_account_id)
            || $settings->stripe_account_id === '') {
            abort(503, 'Saved card management is not configured for this store.');
        }

        return $settings;
    }

    private function stripeSecret(): string
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || ! str_starts_with($secret, 'sk_')) {
            abort(503, 'Stripe is not configured for this store.');
        }

        return $secret;
    }

    /**
     * @return array<string, mixed>
     */
    private function methodData(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'provider' => $method->provider,
            'card_brand' => $method->card_brand,
            'card_last4' => $method->card_last4,
            'card_exp_month' => $method->card_exp_month,
            'card_exp_year' => $method->card_exp_year,
            'is_default' => (bool) $method->is_default,
            'created_at' => $method->created_at,
        ];
    }
}
