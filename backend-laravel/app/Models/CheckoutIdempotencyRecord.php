<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutIdempotencyRecord extends TenantModel
{
    public $timestamps = false;

    protected $hidden = ['guest_cart_token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
