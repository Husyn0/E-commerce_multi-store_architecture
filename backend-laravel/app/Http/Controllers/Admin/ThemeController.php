<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\TenantSettings;
use App\Services\AuditLogger;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

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

    public function uploadHeroImage(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
        ]);

        $tenantId = $this->tenantId();
        $path = $request->file('image')->store("tenants/{$tenantId}/homepage", 'public');
        if (! is_string($path)) {
            return response()->json(['message' => 'The hero image could not be stored.'], 500);
        }
        $url = Storage::disk('public')->url($path);

        try {
            $oldImageUrl = DB::transaction(function () use ($auditLogger, $path, $request, $tenantId, $url): ?string {
                $settings = TenantSettings::query()
                    ->forTenant($tenantId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $themeConfig = $settings->theme_config ?? [];
                $content = is_array($themeConfig['content'] ?? null) ? $themeConfig['content'] : [];
                $oldImageUrl = is_string($content['hero_image_url'] ?? null) ? $content['hero_image_url'] : null;
                $content['hero_image_url'] = $url;
                $themeConfig['content'] = $content;
                $settings->theme_config = $themeConfig;
                $settings->save();

                $auditLogger->web(
                    $this->tenantContext(),
                    $request->user(),
                    'theme.hero_image.updated',
                    'tenant_settings',
                    $settings->id,
                    $request,
                );

                return $oldImageUrl;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        $oldPath = $this->managedHeroImagePath($oldImageUrl, $tenantId);
        if ($oldPath !== null && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['hero_image_url' => $url])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        if (array_diff(array_keys($request->all()), ['theme_config']) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }

        $data = $request->validate([
            'theme_config' => ['required', 'array:colors,fonts,logo_url,layout_variant,content'],
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
            'theme_config.content' => ['sometimes', 'array:hero_eyebrow,hero_title,hero_emphasis,hero_description,hero_cta_label,hero_image_url,hero_product_id,story_title,story_emphasis,story_caption'],
            'theme_config.content.hero_eyebrow' => ['sometimes', 'string', 'min:1', 'max:100'],
            'theme_config.content.hero_title' => ['sometimes', 'string', 'min:1', 'max:120'],
            'theme_config.content.hero_emphasis' => ['sometimes', 'string', 'min:1', 'max:100'],
            'theme_config.content.hero_description' => ['sometimes', 'string', 'min:1', 'max:320'],
            'theme_config.content.hero_cta_label' => ['sometimes', 'string', 'min:1', 'max:60'],
            'theme_config.content.hero_image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'theme_config.content.hero_product_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('products', 'id')->where(fn (Builder $query) => $query
                    ->where('tenant_id', $this->tenantId())
                    ->where('is_active', true)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())),
            ],
            'theme_config.content.story_title' => ['sometimes', 'string', 'min:1', 'max:120'],
            'theme_config.content.story_emphasis' => ['sometimes', 'string', 'min:1', 'max:100'],
            'theme_config.content.story_caption' => ['sometimes', 'string', 'min:1', 'max:240'],
        ]);

        $oldHeroImagePath = null;
        $updated = DB::transaction(function () use ($auditLogger, $data, $request, &$oldHeroImagePath): bool {
            $settings = TenantSettings::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->firstOrFail();

            if ($settings->theme_config === $data['theme_config']) {
                return false;
            }

            $oldHeroImageUrl = $settings->theme_config['content']['hero_image_url'] ?? null;
            $oldHeroImagePath = $this->managedHeroImagePath(
                is_string($oldHeroImageUrl) ? $oldHeroImageUrl : null,
                $this->tenantId(),
            );

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

        $newHeroImageUrl = $data['theme_config']['content']['hero_image_url'] ?? null;
        $newHeroImagePath = $this->managedHeroImagePath(
            is_string($newHeroImageUrl) ? $newHeroImageUrl : null,
            $this->tenantId(),
        );
        if ($updated && $oldHeroImagePath !== null && $oldHeroImagePath !== $newHeroImagePath) {
            Storage::disk('public')->delete($oldHeroImagePath);
        }

        return response()->json(['updated' => $updated]);
    }

    private function managedHeroImagePath(?string $url, string $tenantId): ?string
    {
        if ($url === null) {
            return null;
        }

        $urlPath = parse_url($url, PHP_URL_PATH);
        $path = is_string($urlPath) ? preg_replace('#^/storage/#', '', $urlPath) : null;
        $prefix = "tenants/{$tenantId}/homepage/";

        return is_string($path) && str_starts_with($path, $prefix) ? $path : null;
    }
}
