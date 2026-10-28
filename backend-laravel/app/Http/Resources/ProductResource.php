<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'sku' => $this->sku,
            'price_cents' => (int) $this->price_cents,
            'compare_at_price_cents' => $this->compare_at_price_cents === null
                ? null
                : (int) $this->compare_at_price_cents,
            'currency' => $this->currency,
            'stock' => (int) $this->stock,
            'weight_grams' => $this->weight_grams,
            'is_active' => (bool) $this->is_active,
            'published_at' => $this->published_at,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
