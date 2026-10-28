<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\Tenant;
use App\Models\TenantSettings;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends TenantApiController
{
    public function show(): JsonResponse
    {
        $tenant = $this->tenantContext()->tenant;
        $settings = TenantSettings::query()->forTenant($tenant->id)->firstOrFail();
        $accountId = $settings->stripe_account_id;

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'default_locale' => $tenant->default_locale,
                'default_currency' => $tenant->default_currency,
            ],
            'settings' => [
                'theme_config' => $settings->theme_config,
                'require_email_verification' => $settings->require_email_verification,
                'require_phone_verification' => $settings->require_phone_verification,
                'payment_driver' => $settings->payment_driver,
                'verification_driver' => $settings->verification_driver,
                'human_check_driver' => $settings->human_check_driver,
                'email_provider' => $settings->email_provider,
                'sms_provider' => $settings->sms_provider,
                'stripe_account_id_set' => $accountId !== null,
                'stripe_account_id_masked' => $accountId === null
                    ? null
                    : '********'.substr($accountId, -4),
            ],
            'driver_warning' => $this->hasDemoOrLogDriver($settings),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $allowed = [
            'name',
            'default_locale',
            'require_email_verification',
            'require_phone_verification',
        ];
        $this->rejectUnexpectedKeys($request, $allowed);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'default_locale' => ['sometimes', 'required', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/', 'max:5'],
            'require_email_verification' => ['sometimes', 'boolean'],
            'require_phone_verification' => ['sometimes', 'boolean'],
        ]);

        $changed = DB::transaction(function () use ($auditLogger, $data, $request): bool {
            $tenant = Tenant::query()->whereKey($this->tenantId())->lockForUpdate()->firstOrFail();
            $settings = TenantSettings::query()->forTenant($tenant->id)->lockForUpdate()->firstOrFail();
            $tenant->fill(array_intersect_key($data, array_flip(['name', 'default_locale'])));
            $settings->fill(array_intersect_key($data, array_flip([
                'require_email_verification',
                'require_phone_verification',
            ])));
            $tenant->save();
            $settings->save();

            if (! $tenant->wasChanged() && ! $settings->wasChanged()) {
                return false;
            }

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'settings.updated',
                'tenant_settings',
                $settings->id,
                $request,
                ['fields' => array_keys($data)],
            );

            return true;
        });

        return response()->json(['updated' => $changed]);
    }

    private function rejectUnexpectedKeys(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_keys($request->all()), $allowed);
        if ($unexpected !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }
    }

    private function hasDemoOrLogDriver(TenantSettings $settings): bool
    {
        return $settings->payment_driver === 'demo'
            || $settings->verification_driver === 'demo'
            || $settings->human_check_driver === 'demo'
            || $settings->email_provider === 'log'
            || $settings->sms_provider === 'log';
    }
}
