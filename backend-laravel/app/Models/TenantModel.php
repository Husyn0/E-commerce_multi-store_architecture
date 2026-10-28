<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class TenantModel extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = ['id', 'tenant_id'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where($this->qualifyColumn('tenant_id'), $tenantId);
    }
}
