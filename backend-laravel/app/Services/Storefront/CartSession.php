<?php

namespace App\Services\Storefront;

use App\Models\Cart;
use App\Models\User;

final readonly class CartSession
{
    public function __construct(
        public Cart $cart,
        public ?string $token,
        public bool $created,
        public ?User $user = null,
    ) {}
}
