<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends TenantModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
