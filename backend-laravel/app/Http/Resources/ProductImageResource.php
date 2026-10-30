<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->publicMediaUrl($this->url),
            'media_type' => $this->media_type ?? 'image',
            'alt_text' => $this->alt_text,
            'position' => $this->position,
        ];
    }

    private function publicMediaUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/storage/tenants/')) {
            return Storage::disk('public')->url(substr($path, strlen('/storage/')));
        }

        return $url;
    }
}
