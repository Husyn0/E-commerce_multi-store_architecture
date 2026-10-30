<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'image_url' => $this->publicMediaUrl($this->image_url),
            'position' => (int) $this->position,
            'is_active' => (bool) $this->is_active,
        ];
    }

    private function publicMediaUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/tenants/')) {
            return Storage::disk('public')->url(substr($path, strlen('/storage/')));
        }

        return $url;
    }
}
