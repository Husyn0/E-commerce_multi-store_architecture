<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\TenantSettings;
use App\Models\User;
use App\Services\Tenancy\TenantLifecycleService;
use App\Services\Verification\VerificationCodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetTenantAdminPassword extends Command
{
    protected $signature = 'tenant:reset-admin-password {tenantId : Tenant UUID} {email : Admin email address}';

    protected $description = 'Issue a short-lived reset OTP to the current TTY when email delivery is unavailable.';

    public function handle(
        TenantLifecycleService $lifecycle,
        VerificationCodeService $verificationCodes,
    ): int {
        $operatorRef = $lifecycle->assertEnabled();
        if (! function_exists('stream_isatty') || ! stream_isatty(STDIN) || ! stream_isatty(STDOUT)) {
            $this->error('This fallback is TTY-only; no reset code was issued or displayed.');

            return self::FAILURE;
        }

        $tenantId = (string) $this->argument('tenantId');
        $email = Str::lower(trim((string) $this->argument('email')));
        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->first();

        if ($user === null) {
            $this->error('No active Admin account matches that tenant and email.');

            return self::FAILURE;
        }

        $settings = TenantSettings::query()->forTenant($tenantId)->firstOrFail();
        $code = DB::transaction(function () use ($operatorRef, $tenantId, $user, $settings, $verificationCodes): ?string {
            $code = $verificationCodes->issueCliAdminPasswordReset($settings, $user);
            if ($code === null) {
                return null;
            }

            $audit = new AuditLog;
            $audit->tenant_id = $tenantId;
            $audit->actor_id = null;
            $audit->action = 'admin.password_reset_otp_issued';
            $audit->entity_type = 'user';
            $audit->entity_id = $user->id;
            $audit->source = 'cli';
            $audit->host_type = null;
            $audit->metadata = ['delivery' => 'tty', 'operator_ref' => $operatorRef];
            $audit->save();

            return $code;
        });
        if ($code === null) {
            $this->error('Reset OTP rate limit reached. No code was issued.');

            return self::FAILURE;
        }

        $this->line("One-time password-reset code (expires in 10 minutes): {$code}");
        $this->warn('Pass this code to the Admin through a secure channel. It is not written to application logs.');

        return self::SUCCESS;
    }
}
