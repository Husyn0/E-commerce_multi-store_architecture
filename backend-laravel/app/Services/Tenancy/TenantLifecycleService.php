<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class TenantLifecycleService
{
    public function provision(
        string $name,
        string $subdomain,
        string $currency,
        string $locale,
        string $adminEmail,
    ): Tenant {
        $operatorRef = $this->assertEnabled();
        $name = trim($name);
        $subdomain = strtolower(trim($subdomain));
        $currency = strtoupper(trim($currency));
        $locale = trim($locale);
        $adminEmail = strtolower(trim($adminEmail));

        if ($name === '' || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Tenant name must be between 1 and 160 characters.');
        }
        if (preg_match('/^(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain) !== 1) {
            throw new InvalidArgumentException('Subdomain must be a valid DNS label.');
        }
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must be a three-letter ISO code.');
        }
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Locale must use a supported two-letter or language-region format.');
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL) === false || strlen($adminEmail) > 254) {
            throw new InvalidArgumentException('A valid Admin email address is required.');
        }

        return DB::transaction(function () use ($adminEmail, $currency, $locale, $name, $operatorRef, $subdomain): Tenant {
            $tenant = new Tenant;
            $tenant->id = (string) Str::uuid();
            $tenant->name = $name;
            $tenant->subdomain = $subdomain;
            $tenant->status = 'active';
            $tenant->default_currency = $currency;
            $tenant->default_locale = $locale;
            $tenant->save();

            $settings = new TenantSettings;
            $settings->tenant_id = $tenant->id;
            $settings->theme_config = [];
            $settings->payment_driver = config('services.defaults.payment_driver', 'demo');
            $settings->verification_driver = config('services.defaults.verification_driver', 'demo');
            $settings->human_check_driver = config('services.defaults.human_check_driver', 'demo');
            $settings->email_provider = config('services.defaults.email_provider', 'log');
            $settings->sms_provider = config('services.defaults.sms_provider', 'log');
            $settings->require_email_verification = true;
            $settings->require_phone_verification = false;
            $settings->save();

            $admin = new User;
            $admin->id = (string) Str::uuid();
            $admin->tenant_id = $tenant->id;
            $admin->email = $adminEmail;
            $admin->password_hash = Hash::make(Str::random(96));
            $admin->role = 'admin';
            $admin->status = 'active';
            $admin->save();

            $this->audit($tenant->id, 'tenant.provisioned', [
                'admin_email' => $adminEmail,
                'credential_delivery' => 'admin_password_reset_otp',
                'operator_ref' => $operatorRef,
            ]);

            return $tenant;
        });
    }

    public function suspend(string $tenantId): bool
    {
        $operatorRef = $this->assertEnabled();

        return DB::transaction(function () use ($operatorRef, $tenantId): bool {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            if ($tenant->status === 'suspended') {
                return false;
            }

            $tenant->status = 'suspended';
            $tenant->save();
            UserSession::query()
                ->forTenant($tenant->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $this->audit($tenant->id, 'tenant.suspended', ['operator_ref' => $operatorRef]);

            return true;
        });
    }

    public function recover(string $tenantId): bool
    {
        $operatorRef = $this->assertEnabled();

        return DB::transaction(function () use ($operatorRef, $tenantId): bool {
            $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
            if ($tenant->status === 'active') {
                return false;
            }

            $tenant->status = 'active';
            $tenant->save();
            $this->audit($tenant->id, 'tenant.recovered', ['operator_ref' => $operatorRef]);

            return true;
        });
    }

    public function assertEnabled(): string
    {
        if (! config('tenancy.lifecycle_cli_enabled')) {
            throw new RuntimeException('Tenant lifecycle commands are disabled.');
        }

        $operatorRef = (string) config('tenancy.cli_operator_ref');
        if ($operatorRef === '') {
            throw new RuntimeException('TENANT_CLI_OPERATOR_REF must identify the operator.');
        }

        return $operatorRef;
    }

    private function audit(string $tenantId, string $action, array $metadata): void
    {
        $audit = new AuditLog;
        $audit->tenant_id = $tenantId;
        $audit->actor_id = null;
        $audit->action = $action;
        $audit->entity_type = 'tenant';
        $audit->entity_id = $tenantId;
        $audit->source = 'cli';
        $audit->host_type = null;
        $audit->metadata = $metadata;
        $audit->save();
    }
}
