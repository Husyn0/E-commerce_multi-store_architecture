<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends TenantModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
