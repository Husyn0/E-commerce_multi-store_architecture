<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductVariantController extends TenantApiController
{
    public function store(Request $request, string $productId, AuditLogger $auditLogger): JsonResponse
    {
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $data = $this->validatedVariant($request);
        $variant = DB::transaction(function () use ($auditLogger, $data, $product, $request): ProductVariant {
            $lockedProduct = Product::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->findOrFail($product->id);
            $this->assertAttributesUnique($lockedProduct, $data['attributes']);
            $variant = new ProductVariant;
            $variant->tenant_id = $this->tenantId();
            $variant->product_id = $lockedProduct->id;
            $variant->fill($data);
            $variant->save();
            $lockedProduct->stock = 0;
            $lockedProduct->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.variant.created',
                'product_variant',
                $variant->id,
                $request,
            );

            return $variant;
        });

        return (new ProductResource($variant->product->load(['images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Request $request,
        string $productId,
        string $variantId,
        AuditLogger $auditLogger,
    ): ProductResource {
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $variant = ProductVariant::query()
            ->forTenant($this->tenantId())
            ->where('product_id', $product->id)
            ->findOrFail($variantId);
        $data = $this->validatedVariant($request, $variant->id);
        if ($data === []) {
            return new ProductResource($product->load(['images', 'variants']));
        }

        DB::transaction(function () use ($auditLogger, $data, $product, $request, $variant): void {
            $lockedProduct = Product::query()
                ->forTenant($this->tenantId())
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedVariant = ProductVariant::query()
                ->forTenant($this->tenantId())
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->findOrFail($variant->id);
            if (isset($data['attributes'])) {
                $this->assertAttributesUnique($lockedProduct, $data['attributes'], $lockedVariant->id);
            }
            $lockedVariant->fill($data);
            $lockedVariant->save();
            if ($lockedVariant->wasChanged()) {
                $auditLogger->web(
                    $this->tenantContext(),
                    $request->user(),
                    'product.variant.updated',
                    'product_variant',
                    $lockedVariant->id,
                    $request,
                );
            }
        });

        return new ProductResource($product->fresh()->load(['images', 'variants']));
    }

    public function destroy(
        Request $request,
        string $productId,
        string $variantId,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $variant = ProductVariant::query()
            ->forTenant($this->tenantId())
            ->where('product_id', $product->id)
            ->findOrFail($variantId);
        $blocked = DB::transaction(function () use ($auditLogger, $product, $request, $variant): bool {
            Product::query()
                ->forTenant($this->tenantId())
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedVariant = ProductVariant::query()
                ->forTenant($this->tenantId())
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->findOrFail($variant->id);
            if ($lockedVariant->cartItems()->exists()) {
                return true;
            }
            $id = $lockedVariant->id;
            $lockedVariant->delete();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.variant.deleted',
                'product_variant',
                $id,
                $request,
            );

            return false;
        });
        if ($blocked) {
            return response()->json([
                'message' => 'This variant is in an active cart and cannot be removed.',
            ], 409);
        }

        return response()->json([
            'product' => (new ProductResource($product->fresh()->load(['images', 'variants'])))->resolve(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedVariant(Request $request, ?string $ignoreId = null): array
    {
        $allowed = ['sku', 'name', 'attributes', 'price_cents', 'stock'];
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }
        $sku = Rule::unique('product_variants', 'sku')->where('tenant_id', $this->tenantId());
        if ($ignoreId !== null) {
            $sku->ignore($ignoreId);
        }
        $required = $ignoreId === null ? 'required' : 'sometimes';
        $data = $request->validate([
            'sku' => [$required, 'string', 'max:100', $sku],
            'name' => [$required, 'string', 'max:160'],
            'attributes' => [$required, 'array', 'min:1', 'max:20'],
            'attributes.*' => ['string', 'max:120'],
            'price_cents' => [$required, 'integer', 'min:0'],
            'stock' => ['sometimes', 'integer', 'min:0'],
        ]);
        if ($ignoreId !== null && $data === []) {
            throw ValidationException::withMessages(['variant' => 'At least one variant field must be provided.']);
        }

        return $data;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function assertAttributesUnique(Product $product, array $attributes, ?string $ignoreId = null): void
    {
        $canonical = $this->canonicalAttributes($attributes);
        $variants = ProductVariant::query()
            ->forTenant($this->tenantId())
            ->where('product_id', $product->id)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '<>', $ignoreId))
            ->lockForUpdate()
            ->get(['id', 'attributes']);

        foreach ($variants as $variant) {
            if ($this->canonicalAttributes($variant->attributes ?? []) === $canonical) {
                throw ValidationException::withMessages([
                    'attributes' => 'A variant with this attribute combination already exists for the product.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, string>  $attributes
     * @return array<string, string>
     */
    private function canonicalAttributes(array $attributes): array
    {
        ksort($attributes, SORT_STRING);

        return $attributes;
    }
}
