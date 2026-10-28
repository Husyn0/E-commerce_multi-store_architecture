<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends TenantModel
{
    public $timestamps = false;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
