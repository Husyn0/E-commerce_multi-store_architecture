<?php

namespace App\Tenancy;

use App\Models\Tenant;

class EloquentTenantHostLocator implements TenantHostLocator
{
    public function findBySubdomain(string $subdomain): ?Tenant
    {
        return Tenant::query()->where('subdomain', $subdomain)->first();
    }

    public function findByCustomDomain(string $domain): ?Tenant
    {
        return Tenant::query()->where('custom_domain', $domain)->first();
    }
}
