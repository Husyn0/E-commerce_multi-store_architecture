<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends TenantApiController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'string', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $products = Product::query()
            ->forTenant($this->tenantId())
            ->with(['images', 'variants'])
            ->when(isset($filters['q']), function ($query) use ($filters): void {
                $term = '%'.addcslashes($filters['q'], '\\%_').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery->where('title', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhere('sku', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 20);

        return ProductResource::collection($products);
    }

    public function store(Request $request, AuditLogger $auditLogger)
    {
        $data = $this->validatedProduct($request);
        $this->assertCategoryBelongsToTenant($data['category_id'] ?? null);
        $this->assertComparePrice($data);

        $product = DB::transaction(function () use ($data, $auditLogger, $request): Product {
            $product = new Product;
            $product->tenant_id = $this->tenantId();
            $product->fill($data);
            $product->currency = $this->tenantContext()->tenant->default_currency;
            $product->save();

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.created',
                'product',
                $product->id,
                $request,
            );

            return $product;
        });

        return (new ProductResource($product->load(['images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(string $productId): ProductResource
    {
        $product = Product::query()
            ->forTenant($this->tenantId())
            ->with(['images', 'variants'])
            ->findOrFail($productId);

        return new ProductResource($product);
    }

    public function update(Request $request, string $productId, AuditLogger $auditLogger): ProductResource
    {
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $data = $this->validatedProduct($request, $product->id);
        $this->assertCategoryBelongsToTenant($data['category_id'] ?? null);
        $this->assertComparePrice($data, $product);

        if ($data === []) {
            return new ProductResource($product->load(['images', 'variants']));
        }

        DB::transaction(function () use ($data, $auditLogger, $product, $request): void {
            $originalPublicationState = $product->is_active && $product->published_at !== null;
            $product->fill($data);
            $newPublicationState = $product->is_active && $product->published_at !== null;
            $product->save();

            if ($product->wasChanged()) {
                $action = $originalPublicationState !== $newPublicationState
                    ? ($newPublicationState ? 'product.published' : 'product.unpublished')
                    : 'product.updated';

                $auditLogger->web(
                    $this->tenantContext(),
                    $request->user(),
                    $action,
                    'product',
                    $product->id,
                    $request,
                );
            }
        });

        return new ProductResource($product->refresh()->load(['images', 'variants']));
    }

    public function destroy(string $productId, Request $request, AuditLogger $auditLogger): JsonResponse|Response
    {
        $blocked = DB::transaction(function () use ($auditLogger, $productId, $request): bool {
            $product = Product::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->findOrFail($productId);
            if ($product->cartItems()->exists() || $product->variants()->whereHas('cartItems')->exists()) {
                return true;
            }
            $id = $product->id;
            $product->variants()->delete();
            $product->images()->delete();
            $product->delete();

            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.deleted',
                'product',
                $id,
                $request,
            );

            return false;
        });
        if ($blocked) {
            return response()->json([
                'message' => 'This product is in an active cart. Deactivate it instead.',
            ], 409);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedProduct(Request $request, ?string $ignoreId = null): array
    {
        $tenantId = $this->tenantId();
        $allowed = [
            'category_id',
            'title',
            'slug',
            'description',
            'sku',
            'price_cents',
            'compare_at_price_cents',
            'weight_grams',
            'is_active',
            'published_at',
        ];
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }

        $slugRule = Rule::unique('products', 'slug')->where('tenant_id', $tenantId);
        $skuRule = Rule::unique('products', 'sku')->where('tenant_id', $tenantId);
        if ($ignoreId !== null) {
            $slugRule->ignore($ignoreId);
            $skuRule->ignore($ignoreId);
        }
        $required = $ignoreId === null ? 'required' : 'sometimes';

        return $request->validate([
            'category_id' => [
                'sometimes',
                'nullable',
                'uuid',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'title' => [$required, 'string', 'max:240'],
            'slug' => [
                $required,
                'string',
                'max:240',
                $slugRule,
            ],
            'description' => [$required, 'string'],
            'sku' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                $skuRule,
            ],
            'price_cents' => [$required, 'integer', 'min:0'],
            'compare_at_price_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'weight_grams' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'published_at' => ['sometimes', 'nullable', 'date'],
        ]);
    }

    private function assertComparePrice(array $data, ?Product $product = null): void
    {
        $compareAtPrice = $data['compare_at_price_cents'] ?? $product?->compare_at_price_cents;
        $price = $data['price_cents'] ?? $product?->price_cents;

        if ($compareAtPrice !== null && $price !== null && (int) $compareAtPrice <= (int) $price) {
            throw ValidationException::withMessages([
                'compare_at_price_cents' => 'The compare-at price must be greater than the product price.',
            ]);
        }
    }

    private function assertCategoryBelongsToTenant(?string $categoryId): void
    {
        if ($categoryId !== null) {
            Category::query()->forTenant($this->tenantId())->findOrFail($categoryId);
        }
    }
}
