<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends TenantApiController
{
    public function categories()
    {
        return CategoryResource::collection(
            Category::query()
                ->forTenant($this->tenantId())
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')
                ->get(),
        );
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'max:180'],
            'q' => ['sometimes', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $products = Product::query()
            ->forTenant($this->tenantId())
            ->with(['images', 'variants'])
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->when(isset($filters['category']), function ($query) use ($filters): void {
                $query->whereHas('category', function ($categoryQuery) use ($filters): void {
                    $categoryQuery->where('tenant_id', $this->tenantId())
                        ->where('slug', $filters['category'])
                        ->where('is_active', true);
                });
            })
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $term = '%'.addcslashes($filters['q'], '\\%_').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery->where('title', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return ProductResource::collection($products);
    }

    public function show(string $slug): ProductResource
    {
        $product = Product::query()
            ->forTenant($this->tenantId())
            ->with(['images', 'variants'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->firstOrFail();

        return new ProductResource($product);
    }
}
