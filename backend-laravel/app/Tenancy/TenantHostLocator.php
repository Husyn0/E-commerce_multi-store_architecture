<?php

namespace App\Tenancy;

use App\Models\Tenant;

interface TenantHostLocator
{
    public function findBySubdomain(string $subdomain): ?Tenant;

    public function findByCustomDomain(string $domain): ?Tenant;
}
