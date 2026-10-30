<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['url', 'alt_text', 'position', 'media_type'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
