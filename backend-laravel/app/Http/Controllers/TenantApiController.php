<?php

namespace App\Http\Controllers;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantManager;
use LogicException;

abstract class TenantApiController extends Controller
{
    public function __construct(protected readonly TenantManager $tenantManager) {}

    protected function tenantContext(): TenantContext
    {
        return $this->tenantManager->current()
            ?? throw new LogicException('Tenant context has not been resolved.');
    }

    protected function tenantId(): string
    {
        return $this->tenantContext()->tenant->id;
    }
}
