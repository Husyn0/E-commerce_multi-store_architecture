<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends TenantModel
{
    protected function casts(): array
    {
        return [
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'checkout_idempotency_expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function idempotencyRecord(): HasMany
    {
        return $this->hasMany(CheckoutIdempotencyRecord::class);
    }

    public function refundedTotalCents(): int
    {
        return (int) $this->refunds()
            ->where('status', 'succeeded')
            ->sum('amount_cents');
    }
}
