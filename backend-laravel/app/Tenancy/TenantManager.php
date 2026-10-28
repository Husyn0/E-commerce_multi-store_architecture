<?php

namespace App\Tenancy;

use LogicException;

class TenantManager
{
    private ?TenantContext $context = null;

    public function set(TenantContext $context): void
    {
        $this->context = $context;
    }

    public function current(): ?TenantContext
    {
        return $this->context;
    }

    public function requireStatus(): string
    {
        return $this->context?->tenant->status
            ?? throw new LogicException('A tenant must be resolved before reading its status.');
    }
}
