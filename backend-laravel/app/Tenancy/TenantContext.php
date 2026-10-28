<?php

namespace App\Tenancy;

use App\Models\Tenant;

class TenantContext
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $hostType,
    ) {}
}
