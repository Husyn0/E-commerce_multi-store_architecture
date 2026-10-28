<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends TenantModel
{
    protected $guarded = ['id', 'tenant_id', 'variant_key'];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
