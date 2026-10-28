<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThemeController extends TenantApiController
{
    public function show(): JsonResponse
    {
        return response()->json([
            'theme_config' => TenantSettings::query()
                ->forTenant($this->tenantId())
                ->value('theme_config'),
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['theme_config']) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }

        $data = $request->validate([
            'theme_config' => ['required', 'array:colors,fonts,logo_url,layout_variant'],
            'theme_config.colors' => ['sometimes', 'array:primary,secondary,accent,background,text'],
            'theme_config.colors.primary' => ['sometimes', 'string', 'max:40'],
            'theme_config.colors.secondary' => ['sometimes', 'string', 'max:40'],
            'theme_config.colors.accent' => ['sometimes', 'string', 'max:40'],
            'theme_config.colors.background' => ['sometimes', 'string', 'max:40'],
            'theme_config.colors.text' => ['sometimes', 'string', 'max:40'],
            'theme_config.fonts' => ['sometimes', 'array:heading,body'],
            'theme_config.fonts.heading' => ['sometimes', 'string', 'max:80'],
            'theme_config.fonts.body' => ['sometimes', 'string', 'max:80'],
            'theme_config.logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'theme_config.layout_variant' => ['sometimes', 'string', 'max:40'],
        ]);

        $updated = DB::transaction(function () use ($auditLogger, $data, $request): bool {
            $settings = TenantSettings::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->firstOrFail();

            if ($settings->theme_config === $data['theme_config']) {
                return false;
            }

            $settings->theme_config = $data['theme_config'];
            $settings->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'theme.updated',
                'tenant_settings',
                $settings->id,
                $request,
            );

            return true;
        });

        return response()->json(['updated' => $updated]);
    }
}
