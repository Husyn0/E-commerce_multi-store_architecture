<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Console\Command;

class SuspendTenant extends Command
{
    protected $signature = 'tenant:suspend {tenantId : Tenant UUID}';

    protected $description = 'Suspend a tenant and revoke its active user sessions.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        if (! $lifecycle->suspend((string) $this->argument('tenantId'))) {
            $this->info('Tenant is already suspended.');

            return self::SUCCESS;
        }

        $this->info('Tenant suspended; all active tenant sessions were revoked.');

        return self::SUCCESS;
    }
}
