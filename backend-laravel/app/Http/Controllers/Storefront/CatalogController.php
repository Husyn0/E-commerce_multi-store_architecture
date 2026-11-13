<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Support\TenantReadCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends TenantApiController
{
    public function categories(Request $request, TenantReadCache $cache): JsonResponse
    {
        $tenantId = $this->tenantId();
        $data = $cache->remember($tenantId, $request->getHost(), 'categories', [], function () use ($tenantId): array {
            return CategoryResource::collection(
                Category::query()
                    ->forTenant($tenantId)
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('name')
                    ->get(),
            )->response()->getData(true);
        });

        return response()->json($data);
    }

    public function index(Request $request, TenantReadCache $cache): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'string', 'max:180'],
            'q' => ['sometimes', 'string', 'max:120'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $tenantId = $this->tenantId();
        $data = $cache->remember($tenantId, $request->getHost(), 'products', $filters, function () use ($filters, $tenantId): array {
            $products = Product::query()
                ->forTenant($tenantId)
                ->with(['images', 'variants'])
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->when(isset($filters['category']), function ($query) use ($filters, $tenantId): void {
                    $query->whereHas('category', function ($categoryQuery) use ($filters, $tenantId): void {
                        $categoryQuery->where('tenant_id', $tenantId)
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

            return ProductResource::collection($products)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function featured(Request $request, TenantReadCache $cache): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $tenantId = $this->tenantId();
        $data = $cache->remember($tenantId, $request->getHost(), 'featured-products', $filters, function () use ($filters, $tenantId): array {
            $now = now();
            $products = Product::query()
                ->forTenant($tenantId)
                ->with(['images', 'variants'])
                ->where('is_featured', true)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', $now)
                ->where(fn ($query) => $query
                    ->whereNull('featured_from')
                    ->orWhere('featured_from', '<=', $now))
                ->where(fn ($query) => $query
                    ->whereNull('featured_until')
                    ->orWhere('featured_until', '>=', $now))
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 100);

            return ProductResource::collection($products)->response()->getData(true);
        });

        return response()->json($data);
    }

    public function show(string $slug, Request $request, TenantReadCache $cache): JsonResponse
    {
        $tenantId = $this->tenantId();
        $data = $cache->remember($tenantId, $request->getHost(), 'product-detail', ['slug' => $slug], function () use ($tenantId, $slug): array {
            $product = Product::query()
                ->forTenant($tenantId)
                ->with(['images', 'variants'])
                ->where('slug', $slug)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->firstOrFail();

            return (new ProductResource($product))->response()->getData(true);
        });

        return response()->json($data);
    }

    public function showById(string $productId, Request $request, TenantReadCache $cache): JsonResponse
    {
        $tenantId = $this->tenantId();
        $data = $cache->remember($tenantId, $request->getHost(), 'product-detail', ['id' => $productId], function () use ($tenantId, $productId): array {
            $product = Product::query()
                ->forTenant($tenantId)
                ->with(['images', 'variants'])
                ->whereKey($productId)
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->firstOrFail();

            return (new ProductResource($product))->response()->getData(true);
        });

        return response()->json($data);
    }
}
