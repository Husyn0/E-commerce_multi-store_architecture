<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Console\Command;

class RecoverTenant extends Command
{
    protected $signature = 'tenant:recover {tenantId : Tenant UUID}';

    protected $description = 'Restore a suspended tenant to active status.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        if (! $lifecycle->recover((string) $this->argument('tenantId'))) {
            $this->info('Tenant is already active.');

            return self::SUCCESS;
        }

        $this->info('Tenant recovered to active status.');

        return self::SUCCESS;
    }
}
