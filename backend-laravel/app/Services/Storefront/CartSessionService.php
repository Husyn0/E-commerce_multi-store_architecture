<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\SessionTokenHasher;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartSessionService
{
    public function __construct(
        private readonly SessionTokenHasher $hasher,
        private readonly StorefrontCsrfToken $csrf,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function resolve(Request $request, TenantContext $context): CartSession
    {
        $user = $request->user();
        if ($user instanceof User && $user->role === 'customer' && $user->tenant_id === $context->tenant->id) {
            return $this->resolveForUser($request, $context, $user);
        }

        $token = $request->cookie('storefront_cart');
        if (! is_string($token) || strlen($token) !== 64 || ! ctype_xdigit($token)) {
            $token = bin2hex(random_bytes(32));
            $created = true;
        } else {
            $created = false;
        }

        $hash = $this->hasher->hash($token);
        $cart = Cart::query()
            ->forTenant($context->tenant->id)
            ->whereNull('user_id')
            ->where('session_token_hash', $hash)
            ->first();

        if ($cart !== null && $cart->expires_at->isPast()) {
            $cart->delete();
            $cart = null;
        }

        if ($cart === null) {
            try {
                $cart = new Cart;
                $cart->tenant_id = $context->tenant->id;
                $cart->session_token_hash = $hash;
                $cart->currency = $context->tenant->default_currency;
                $cart->expires_at = now()->addDays(30);
                $cart->save();
            } catch (QueryException $exception) {
                $cart = Cart::query()
                    ->forTenant($context->tenant->id)
                    ->whereNull('user_id')
                    ->where('session_token_hash', $hash)
                    ->first();
                if ($cart === null) {
                    throw $exception;
                }
            }
            $created = true;
        }

        return new CartSession($cart, $token, $created);
    }

    public function refreshExpiry(Cart $cart): void
    {
        $cart->expires_at = now()->addDays(30);
        $cart->save();
    }

    public function responseCookies(Request $request, CartSession $session): array
    {
        $secure = $request->isSecure() || app()->isProduction();
        $cookies = $session->token === null
            ? [cookie('storefront_cart', '', -1, '/', null, $secure, true, false, 'lax')]
            : [cookie('storefront_cart', $session->token, 60 * 24 * 30, '/', null, $secure, true, false, 'lax')];

        $csrf = $request->cookie('storefront_csrf');
        if (! $this->csrf->isValid(is_string($csrf) ? $csrf : null)) {
            $csrf = $this->csrf->issue();
        }

        $cookies[] = cookie('storefront_csrf', $csrf, 60 * 24 * 30, '/', null, $secure, false, false, 'lax');

        return $cookies;
    }

    private function resolveForUser(Request $request, TenantContext $context, User $user): CartSession
    {
        return DB::transaction(function () use ($request, $context, $user): CartSession {
            $lockedUser = User::query()
                ->where('tenant_id', $context->tenant->id)
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();
            $cart = Cart::query()
                ->forTenant($context->tenant->id)
                ->where('user_id', $lockedUser->id)
                ->where('expires_at', '>', now())
                ->orderByDesc('updated_at')
                ->lockForUpdate()
                ->first();
            $guestCart = $this->findGuestCart($request, $context);

            if ($guestCart !== null && $cart !== null && $guestCart->id !== $cart->id) {
                $mergedLineCount = $guestCart->items()->count();
                $this->mergeGuestCart($guestCart, $cart, $context->tenant->id);
                $this->auditLogger->web(
                    $context,
                    $lockedUser,
                    'cart.merged',
                    'cart',
                    $cart->id,
                    $request,
                    ['source' => 'guest', 'merged_line_count' => $mergedLineCount],
                );
                $guestCart->delete();
            } elseif ($guestCart !== null && $cart === null) {
                $guestCart->user_id = $lockedUser->id;
                $guestCart->session_token_hash = null;
                $guestCart->expires_at = now()->addDays(30);
                $guestCart->save();
                $cart = $guestCart;
                $this->auditLogger->web(
                    $context,
                    $lockedUser,
                    'cart.merged',
                    'cart',
                    $cart->id,
                    $request,
                    ['source' => 'guest', 'merged_line_count' => $cart->items()->count()],
                );
            }

            if ($cart === null) {
                $cart = new Cart;
                $cart->tenant_id = $context->tenant->id;
                $cart->user_id = $lockedUser->id;
                $cart->currency = $context->tenant->default_currency;
                $cart->expires_at = now()->addDays(30);
                $cart->save();
            } else {
                $cart->expires_at = now()->addDays(30);
                $cart->save();
            }

            return new CartSession($cart, null, false, $lockedUser);
        });
    }

    private function findGuestCart(Request $request, TenantContext $context): ?Cart
    {
        $token = $request->cookie('storefront_cart');
        if (! is_string($token) || strlen($token) !== 64 || ! ctype_xdigit($token)) {
            return null;
        }

        $cart = Cart::query()
            ->forTenant($context->tenant->id)
            ->whereNull('user_id')
            ->where('session_token_hash', $this->hasher->hash($token))
            ->lockForUpdate()
            ->first();
        if ($cart !== null && $cart->expires_at->isPast()) {
            $cart->delete();

            return null;
        }

        return $cart;
    }

    private function mergeGuestCart(Cart $guestCart, Cart $userCart, string $tenantId): void
    {
        $guestLines = CartItem::query()
            ->forTenant($tenantId)
            ->where('cart_id', $guestCart->id)
            ->orderBy('product_id')
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();

        foreach ($guestLines as $guestLine) {
            $userLineQuery = CartItem::query()
                ->forTenant($tenantId)
                ->where('cart_id', $userCart->id)
                ->where('product_id', $guestLine->product_id);
            $guestLine->variant_id === null
                ? $userLineQuery->whereNull('variant_id')
                : $userLineQuery->where('variant_id', $guestLine->variant_id);
            $userLine = $userLineQuery->lockForUpdate()->first();

            if ($userLine === null) {
                $guestLine->cart_id = $userCart->id;
                $guestLine->save();

                continue;
            }

            if ($guestLine->variant_id !== null) {
                $stock = (int) ProductVariant::query()
                    ->forTenant($tenantId)
                    ->whereKey($guestLine->variant_id)
                    ->lockForUpdate()
                    ->value('stock');
            } else {
                $stock = (int) Product::query()
                    ->forTenant($tenantId)
                    ->whereKey($guestLine->product_id)
                    ->lockForUpdate()
                    ->value('stock');
            }
            $quantity = (int) $userLine->quantity + (int) $guestLine->quantity;
            if ($quantity > $stock) {
                throw ValidationException::withMessages([
                    'cart' => 'Sign-in cart merge exceeds current stock; adjust quantities and retry.',
                ]);
            }

            $userLine->quantity = $quantity;
            $userLine->save();
            $guestLine->delete();
        }
    }
}
