<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Storefront\CartSession;
use App\Services\Storefront\CartSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CartController extends TenantApiController
{
    public function show(Request $request, CartSessionService $sessions): JsonResponse
    {
        $session = $this->resolveSession($request, $sessions);

        return $session instanceof JsonResponse ? $session : $this->respond($request, $session, $sessions);
    }

    public function add(Request $request, CartSessionService $sessions): JsonResponse
    {
        $input = $request->validate([
            'product_id' => ['required', 'uuid'],
            'variant_id' => ['nullable', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price_cents' => ['prohibited'],
        ]);
        $session = $this->resolveSession($request, $sessions);
        if ($session instanceof JsonResponse) {
            return $session;
        }
        $tenantId = $this->tenantId();

        DB::transaction(function () use ($input, $session, $tenantId, $sessions): void {
            $cart = Cart::query()->forTenant($tenantId)->whereKey($session->cart->id)->lockForUpdate()->firstOrFail();
            $product = Product::query()->forTenant($tenantId)->whereKey($input['product_id'])->lockForUpdate()->firstOrFail();
            if (! $product->is_active || $product->published_at === null || $product->published_at->isFuture()) {
                throw ValidationException::withMessages(['product_id' => 'This product is not available.']);
            }

            $hasVariants = ProductVariant::query()
                ->forTenant($tenantId)
                ->where('product_id', $product->id)
                ->exists();
            $variant = null;
            if ($hasVariants && ! isset($input['variant_id'])) {
                throw ValidationException::withMessages(['variant_id' => 'A variant is required for this product.']);
            }
            if (isset($input['variant_id'])) {
                $variant = ProductVariant::query()
                    ->forTenant($tenantId)
                    ->where('product_id', $product->id)
                    ->whereKey($input['variant_id'])
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $price = (int) ($variant?->price_cents ?? $product->price_cents);
            $stock = (int) ($variant?->stock ?? $product->stock);
            $lineQuery = CartItem::query()
                ->forTenant($tenantId)
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id);
            $variant === null
                ? $lineQuery->whereNull('variant_id')
                : $lineQuery->where('variant_id', $variant->id);

            $line = $lineQuery->lockForUpdate()->first();
            $quantity = (int) $input['quantity'] + (int) ($line?->quantity ?? 0);
            if ($quantity > $stock) {
                throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
            }

            if ($line === null) {
                $line = new CartItem;
                $line->tenant_id = $tenantId;
                $line->cart_id = $cart->id;
                $line->product_id = $product->id;
                $line->variant_id = $variant?->id;
            }
            $line->quantity = $quantity;
            $line->unit_price_cents = $price;
            $line->save();
            $sessions->refreshExpiry($cart);
        });

        return $this->respond($request, $session, $sessions);
    }

    public function update(Request $request, string $itemId, CartSessionService $sessions): JsonResponse
    {
        $input = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'unit_price_cents' => ['prohibited'],
        ]);
        $session = $this->resolveSession($request, $sessions);
        if ($session instanceof JsonResponse) {
            return $session;
        }
        $tenantId = $this->tenantId();

        DB::transaction(function () use ($input, $session, $tenantId, $itemId, $sessions): void {
            $cart = Cart::query()->forTenant($tenantId)->whereKey($session->cart->id)->lockForUpdate()->firstOrFail();
            $line = CartItem::query()
                ->forTenant($tenantId)
                ->where('cart_id', $cart->id)
                ->whereKey($itemId)
                ->lockForUpdate()
                ->firstOrFail();
            $stock = $line->variant_id !== null
                ? (int) ProductVariant::query()->forTenant($tenantId)->whereKey($line->variant_id)->value('stock')
                : (int) Product::query()->forTenant($tenantId)->whereKey($line->product_id)->value('stock');
            if ((int) $input['quantity'] > $stock) {
                throw ValidationException::withMessages(['quantity' => 'Requested quantity exceeds available stock.']);
            }

            $line->quantity = $input['quantity'];
            $line->save();
            $sessions->refreshExpiry($cart);
        });

        return $this->respond($request, $session, $sessions);
    }

    public function destroy(Request $request, string $itemId, CartSessionService $sessions): JsonResponse
    {
        $session = $this->resolveSession($request, $sessions);
        if ($session instanceof JsonResponse) {
            return $session;
        }
        CartItem::query()
            ->forTenant($this->tenantId())
            ->where('cart_id', $session->cart->id)
            ->whereKey($itemId)
            ->delete();

        return $this->respond($request, $session, $sessions);
    }

    private function resolveSession(Request $request, CartSessionService $sessions): CartSession|JsonResponse
    {
        try {
            return $sessions->resolve($request, $this->tenantContext());
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json(['message' => 'Cart session security is not configured.'], 503);
        }
    }

    private function respond(Request $request, CartSession $session, CartSessionService $sessions): JsonResponse
    {
        $session->cart->load(['items.product.images', 'items.variant']);

        $items = [];
        $subtotal = 0;
        foreach ($session->cart->items as $item) {
            $quantity = (int) $item->quantity;
            $price = (int) $item->unit_price_cents;
            if ($price > intdiv(PHP_INT_MAX, $quantity)) {
                throw ValidationException::withMessages(['cart' => 'Cart total exceeds the supported range.']);
            }
            $lineTotal = $price * $quantity;
            if ($lineTotal > PHP_INT_MAX - $subtotal) {
                throw ValidationException::withMessages(['cart' => 'Cart total exceeds the supported range.']);
            }
            $subtotal += $lineTotal;
            $items[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'title' => $item->product->title,
                'variant_name' => $item->variant?->name,
                'image_url' => $item->product->images->first()?->url,
                'quantity' => $quantity,
                'unit_price_cents' => $price,
                'line_total_cents' => $lineTotal,
            ];
        }

        return response()->json([
            'id' => $session->cart->id,
            'currency' => $session->cart->currency,
            'items' => $items,
            'subtotal_cents' => $subtotal,
        ])->withCookies($sessions->responseCookies($request, $session))
            ->header('Cache-Control', 'no-store');
    }
}
