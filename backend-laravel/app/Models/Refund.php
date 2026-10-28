<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends TenantModel
{
    public $timestamps = false;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
