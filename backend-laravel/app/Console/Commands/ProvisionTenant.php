<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantLifecycleService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ProvisionTenant extends Command
{
    protected $signature = 'tenant:provision
        {name : Tenant display name}
        {subdomain : Tenant subdomain label}
        {currency : Three-letter currency code}
        {locale : Tenant default locale}
        {--admin-email= : Initial Admin email address}';

    protected $description = 'Provision a tenant and initial Admin without exposing credentials.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $adminEmail = (string) $this->option('admin-email');
        if ($adminEmail === '') {
            $this->error('Provide --admin-email. No credential will be printed.');

            return self::INVALID;
        }

        try {
            $tenant = $lifecycle->provision(
                (string) $this->argument('name'),
                (string) $this->argument('subdomain'),
                (string) $this->argument('currency'),
                (string) $this->argument('locale'),
                $adminEmail,
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->info("Tenant provisioned: {$tenant->id}");
        $this->line('The Admin password is randomly generated and never displayed. The Admin must use the tenant Admin password-reset OTP flow.');

        return self::SUCCESS;
    }
}
