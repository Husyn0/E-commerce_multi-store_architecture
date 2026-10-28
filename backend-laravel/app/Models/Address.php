<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends TenantModel
{
    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
