<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CheckoutIdempotencyRecord;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TenantSettings;
use App\Services\Payment\CheckoutCurrency;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentProviderException;
use App\Services\Storefront\CanonicalRequestHasher;
use App\Services\Storefront\CartSessionService;
use App\Support\SessionTokenHasher;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CheckoutController extends TenantApiController
{
    public function store(
        Request $request,
        CartSessionService $sessions,
        SessionTokenHasher $tokenHasher,
        CanonicalRequestHasher $requestHasher,
        PaymentGatewayManager $gateways,
        CheckoutCurrency $checkoutCurrency,
    ): JsonResponse {
        $key = $request->header('Idempotency-Key');
        if (! is_string($key) || $key === '' || strlen($key) > 255 || preg_match('/^[\x21-\x7E]+$/D', $key) !== 1) {
            return response()->json(['message' => 'A valid Idempotency-Key header is required.'], 400);
        }

        $input = $request->validate([
            'shipping_address' => ['required', 'array'],
            'shipping_address.recipient_name' => ['required', 'string', 'max:160'],
            'shipping_address.phone' => ['nullable', 'string', 'max:32'],
            'shipping_address.line1' => ['required', 'string', 'max:255'],
            'shipping_address.line2' => ['nullable', 'string', 'max:255'],
            'shipping_address.city' => ['required', 'string', 'max:120'],
            'shipping_address.state' => ['nullable', 'string', 'max:120'],
            'shipping_address.postal_code' => ['required', 'string', 'max:32'],
            'shipping_address.country_code' => ['required', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'billing_address' => ['sometimes', 'nullable', 'array'],
            'billing_address.recipient_name' => ['required_with:billing_address', 'string', 'max:160'],
            'billing_address.phone' => ['nullable', 'string', 'max:32'],
            'billing_address.line1' => ['required_with:billing_address', 'string', 'max:255'],
            'billing_address.line2' => ['nullable', 'string', 'max:255'],
            'billing_address.city' => ['required_with:billing_address', 'string', 'max:120'],
            'billing_address.state' => ['nullable', 'string', 'max:120'],
            'billing_address.postal_code' => ['required_with:billing_address', 'string', 'max:32'],
            'billing_address.country_code' => ['required_with:billing_address', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'currency' => ['required', 'string', 'size:3'],
            'customer_email' => ['required', 'email', 'max:254'],
            'customer_phone' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payment_method_id' => ['sometimes', 'prohibited'],
        ]);

        $context = $this->tenantContext();
        $tenantId = $context->tenant->id;
        $settings = TenantSettings::query()->forTenant($tenantId)->first();
        if ($settings === null) {
            return response()->json(['message' => 'Payment processing is not configured for this store.'], 503);
        }
        try {
            $gateway = $gateways->forTenant($settings);
            $session = $sessions->resolve($request, $context);
            $principalType = $session->user === null ? 'guest' : 'user';
            $principalId = $session->user?->id;
            $guestTokenHash = $session->user === null
                ? $tokenHasher->hash($session->token ?? throw new RuntimeException('Guest cart token is missing.'))
                : null;
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Checkout is not configured.'], 503);
        }

        $shipping = $this->normalizeAddress($input['shipping_address']);
        $billing = isset($input['billing_address']) && $input['billing_address'] !== null
            ? $this->normalizeAddress($input['billing_address'])
            : $shipping;
        $currency = strtoupper($input['currency']);
        if (! $checkoutCurrency->supportsTwoMinorUnits($currency)) {
            throw ValidationException::withMessages(['currency' => 'This currency is not supported for checkout.']);
        }
        if ($currency !== $context->tenant->default_currency || $session->cart->currency !== $currency) {
            throw ValidationException::withMessages(['currency' => 'Currency must match the store currency.']);
        }

        $semanticRequest = [
            'cart_reference_id' => $session->cart->id,
            'shipping_address' => $shipping,
            'billing_address' => $input['billing_address'] ?? 'same as shipping',
            'payment_method_id' => null,
            'currency' => $currency,
            'customer_email' => mb_strtolower(trim($input['customer_email'])),
            'customer_phone' => $input['customer_phone'] ?? null,
            'notes' => $input['notes'] ?? null,
        ];
        if (is_array($semanticRequest['billing_address'])) {
            $semanticRequest['billing_address'] = $billing;
        }
        $requestHash = $requestHasher->hash($semanticRequest);
        $paymentDriver = (string) $settings->payment_driver;

        try {
            $checkout = DB::transaction(function () use (
                $tenantId,
                $key,
                $requestHash,
                $guestTokenHash,
                $session,
                $semanticRequest,
                $currency,
                $paymentDriver,
                $principalType,
                $principalId,
            ): array {
                $record = CheckoutIdempotencyRecord::query()
                    ->forTenant($tenantId)
                    ->where('idempotency_key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($record !== null && $record->expires_at->isPast()) {
                    $record->order_id = null;
                    $record->save();
                    $record->delete();
                    $record = null;
                }

                if ($record !== null) {
                    if ($record->principal_type !== $principalType
                        || $record->principal_id !== $principalId
                        || ($principalType === 'guest'
                            && (! is_string($record->guest_cart_token_hash)
                                || ! is_string($guestTokenHash)
                                || ! hash_equals($record->guest_cart_token_hash, $guestTokenHash)))
                        || ! hash_equals($record->request_hash, $requestHash)) {
                        return ['conflict' => true];
                    }
                    if ($record->order_id === null) {
                        return ['in_progress' => true];
                    }

                    $order = Order::query()->forTenant($tenantId)->whereKey($record->order_id)->firstOrFail();

                    return ['record' => $record, 'order' => $order, 'created' => false];
                }

                $cart = Cart::query()
                    ->forTenant($tenantId)
                    ->whereKey($session->cart->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $lines = CartItem::query()
                    ->forTenant($tenantId)
                    ->where('cart_id', $cart->id)
                    ->orderBy('product_id')
                    ->orderBy('variant_id')
                    ->lockForUpdate()
                    ->get();
                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
                }

                $record = new CheckoutIdempotencyRecord;
                $record->tenant_id = $tenantId;
                $record->idempotency_key = $key;
                $record->request_hash = $requestHash;
                $record->principal_type = $principalType;
                $record->principal_id = $principalId;
                $record->guest_cart_token_hash = $guestTokenHash;
                $record->expires_at = now()->addHours(24);
                $record->save();

                $order = new Order;
                $order->tenant_id = $tenantId;
                $order->order_number = (string) Str::ulid();
                $order->status = 'pending';
                $order->payment_status = 'requires_payment';
                $order->payment_provider = $paymentDriver;
                $order->user_id = $principalId;
                $order->checkout_idempotency_key = $key;
                $order->checkout_request_hash = $requestHash;
                $order->checkout_idempotency_expires_at = $record->expires_at;
                $order->subtotal_cents = 0;
                $order->tax_cents = 0;
                $order->shipping_cents = 0;
                $order->discount_cents = 0;
                $order->total_cents = 0;
                $order->currency = $currency;
                $order->shipping_address_snapshot = $semanticRequest['shipping_address'];
                $order->billing_address_snapshot = $semanticRequest['billing_address'] === 'same as shipping'
                    ? $semanticRequest['shipping_address']
                    : $semanticRequest['billing_address'];
                $order->customer_email = $semanticRequest['customer_email'];
                $order->customer_phone = $semanticRequest['customer_phone'];
                $order->notes = $semanticRequest['notes'];
                $order->save();

                $subtotal = 0;
                foreach ($lines as $line) {
                    $product = Product::query()
                        ->forTenant($tenantId)
                        ->whereKey($line->product_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    if (! $product->is_active || $product->published_at === null || $product->published_at->isFuture()) {
                        throw ValidationException::withMessages(['cart' => 'A product in your cart is no longer available.']);
                    }

                    if ($line->variant_id !== null) {
                        $variant = ProductVariant::query()
                            ->forTenant($tenantId)
                            ->where('product_id', $product->id)
                            ->whereKey($line->variant_id)
                            ->lockForUpdate()
                            ->firstOrFail();
                        $stock = (int) $variant->stock;
                        $price = (int) $variant->price_cents;
                        if ((int) $line->quantity > $stock) {
                            throw ValidationException::withMessages(['cart' => 'A product in your cart no longer has enough stock.']);
                        }
                        $variant->stock = $stock - (int) $line->quantity;
                        $variant->save();
                        $variantSnapshot = [
                            'id' => $variant->id,
                            'name' => $variant->name,
                            'sku' => $variant->sku,
                            'attributes' => $variant->attributes,
                        ];
                    } else {
                        $variantCount = ProductVariant::query()
                            ->forTenant($tenantId)
                            ->where('product_id', $product->id)
                            ->count();
                        if ($variantCount > 0) {
                            throw ValidationException::withMessages(['cart' => 'Select a variant for each configured product.']);
                        }
                        $stock = (int) $product->stock;
                        $price = (int) $product->price_cents;
                        if ((int) $line->quantity > $stock) {
                            throw ValidationException::withMessages(['cart' => 'A product in your cart no longer has enough stock.']);
                        }
                        $product->stock = $stock - (int) $line->quantity;
                        $product->save();
                        $variantSnapshot = null;
                    }
                    $quantity = (int) $line->quantity;
                    if ($price > intdiv(PHP_INT_MAX - $subtotal, $quantity)) {
                        throw ValidationException::withMessages(['cart' => 'Order total exceeds the supported range.']);
                    }
                    $lineTotal = $price * $quantity;
                    $subtotal += $lineTotal;
                    $line->unit_price_cents = $price;
                    $line->save();

                    $orderItem = new OrderItem;
                    $orderItem->tenant_id = $tenantId;
                    $orderItem->order_id = $order->id;
                    $orderItem->product_id = $product->id;
                    $orderItem->variant_id = $line->variant_id;
                    $orderItem->product_snapshot = [
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'sku' => $product->sku,
                        'description' => $product->description,
                        'variant' => $variantSnapshot,
                    ];
                    $orderItem->quantity = $line->quantity;
                    $orderItem->unit_price_cents = $price;
                    $orderItem->total_cents = $lineTotal;
                    $orderItem->save();
                }

                if ($subtotal <= 0) {
                    throw ValidationException::withMessages(['cart' => 'The order total must be greater than zero.']);
                }

                $order->subtotal_cents = $subtotal;
                $order->total_cents = $subtotal;
                $order->save();
                $record->order_id = $order->id;
                $record->save();
                $cart->items()->delete();

                return ['record' => $record, 'order' => $order, 'created' => true];
            });
        } catch (QueryException $exception) {
            $winner = CheckoutIdempotencyRecord::query()
                ->forTenant($tenantId)
                ->where('idempotency_key', $key)
                ->first();
            if ($winner === null) {
                throw $exception;
            }
            if ($winner->principal_type !== $principalType
                || $winner->principal_id !== $principalId
                || ($principalType === 'guest'
                    && (! is_string($winner->guest_cart_token_hash)
                        || ! is_string($guestTokenHash)
                        || ! hash_equals($winner->guest_cart_token_hash, $guestTokenHash)))
                || ! hash_equals($winner->request_hash, $requestHash)) {
                return response()->json(['message' => 'Idempotency-Key was already used with a different request.'], 409);
            }
            if ($winner->order_id === null) {
                return response()->json(['message' => 'Checkout is being processed; retry with the same key.'], 409);
            }
            $checkout = [
                'record' => $winner,
                'order' => Order::query()->forTenant($tenantId)->whereKey($winner->order_id)->firstOrFail(),
                'created' => false,
            ];
        }

        if (isset($checkout['conflict'])) {
            return response()->json(['message' => 'Idempotency-Key was already used with a different request.'], 409);
        }
        if (isset($checkout['in_progress'])) {
            return response()->json(['message' => 'Checkout is being processed; retry with the same key.'], 409);
        }
        if ($checkout['order']->payment_provider !== $settings->payment_driver) {
            return response()->json([
                'message' => 'The payment configuration changed; restore the order provider before retrying.',
            ], 503);
        }

        try {
            $intent = $gateway->createIntent(
                $settings,
                $checkout['order'],
                'checkout_'.$checkout['record']->id,
            );
        } catch (PaymentProviderException $exception) {
            report($exception);

            return response()->json(['message' => 'The payment provider could not complete checkout. Retry with the same Idempotency-Key.'], 502);
        }

        $paymentState = DB::transaction(function () use ($tenantId, $checkout, $intent): array {
            $order = Order::query()
                ->forTenant($tenantId)
                ->whereKey($checkout['order']->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($order->payment_intent_id !== null && $order->payment_intent_id !== $intent->id) {
                throw new RuntimeException('Payment provider returned a different intent for the same checkout key.');
            }
            if ($order->status === 'cancelled') {
                return ['order' => $order, 'cancelled' => true];
            }
            $order->payment_intent_id = $intent->id;
            $order->save();

            return ['order' => $order, 'cancelled' => false];
        });
        if ($paymentState['cancelled']) {
            $cancelOrder = $paymentState['order'];
            $cancelOrder->payment_intent_id = $intent->id;
            try {
                $gateway->cancelIntent($settings, $cancelOrder, 'checkout_cancel_'.$checkout['record']->id);
            } catch (PaymentProviderException $exception) {
                report($exception);

                return response()->json(['message' => 'The cancelled order payment could not be finalized. Contact the store.'], 502);
            }
            DB::transaction(function () use ($tenantId, $cancelOrder, $intent): void {
                $order = Order::query()
                    ->forTenant($tenantId)
                    ->whereKey($cancelOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $order->payment_intent_id ??= $intent->id;
                $order->payment_status = 'failed';
                $order->save();
            });

            return response()->json(['message' => 'This order was cancelled before payment started.'], 409);
        }
        $lockedOrder = $paymentState['order'];

        return response()->json([
            'order' => [
                'id' => $lockedOrder->id,
                'order_number' => $lockedOrder->order_number,
                'status' => $lockedOrder->status,
                'payment_status' => $lockedOrder->payment_status,
                'total_cents' => (int) $lockedOrder->total_cents,
                'currency' => $lockedOrder->currency,
            ],
            'payment' => [
                'provider' => $lockedOrder->payment_provider,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->clientSecret,
                'status' => $intent->status,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private function normalizeAddress(array $address): array
    {
        return [
            'recipient_name' => trim($address['recipient_name']),
            'phone' => isset($address['phone']) ? trim($address['phone']) : null,
            'line1' => trim($address['line1']),
            'line2' => isset($address['line2']) ? trim($address['line2']) : null,
            'city' => trim($address['city']),
            'state' => isset($address['state']) ? trim($address['state']) : null,
            'postal_code' => trim($address['postal_code']),
            'country_code' => strtoupper($address['country_code']),
        ];
    }
}
