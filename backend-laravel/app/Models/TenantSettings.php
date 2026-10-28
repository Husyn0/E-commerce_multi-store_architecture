<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends TenantModel
{
    protected $table = 'tenant_settings';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'theme_config' => 'array',
            'require_email_verification' => 'boolean',
            'require_phone_verification' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
