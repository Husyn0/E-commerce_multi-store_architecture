<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImageController extends TenantApiController
{
    public function store(Request $request, string $productId, AuditLogger $auditLogger): JsonResponse
    {
        $data = $this->validatedImage($request);

        $image = DB::transaction(function () use ($auditLogger, $data, $productId, $request): ProductImage {
            $product = Product::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->findOrFail($productId);
            $image = new ProductImage;
            $image->tenant_id = $this->tenantId();
            $image->product_id = $product->id;
            $image->url = $data['url'];
            $image->alt_text = $data['alt_text'] ?? null;
            $image->position = $data['position'] ?? ((int) $product->images()->max('position') + 1);
            $image->created_at = now();
            $image->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.image.created',
                'product_image',
                $image->id,
                $request,
            );

            return $image;
        });

        return (new ProductResource($image->product->load(['images', 'variants'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, string $productId, string $imageId, AuditLogger $auditLogger): ProductResource
    {
        $data = $this->validatedImage($request, partial: true);
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $image = ProductImage::query()
            ->forTenant($this->tenantId())
            ->where('product_id', $product->id)
            ->findOrFail($imageId);

        if ($data === []) {
            return new ProductResource($product->load(['images', 'variants']));
        }

        DB::transaction(function () use ($auditLogger, $data, $image, $request): void {
            $image->fill($data);
            $image->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.image.updated',
                'product_image',
                $image->id,
                $request,
            );
        });

        return new ProductResource($product->fresh()->load(['images', 'variants']));
    }

    public function destroy(
        Request $request,
        string $productId,
        string $imageId,
        AuditLogger $auditLogger,
    ): JsonResponse {
        $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
        $image = ProductImage::query()
            ->forTenant($this->tenantId())
            ->where('product_id', $product->id)
            ->findOrFail($imageId);

        DB::transaction(function () use ($auditLogger, $image, $request): void {
            $id = $image->id;
            $image->delete();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'product.image.deleted',
                'product_image',
                $id,
                $request,
            );
        });

        return response()->json([
            'product' => (new ProductResource($product->fresh()->load(['images', 'variants'])))->resolve(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedImage(Request $request, bool $partial = false): array
    {
        $allowed = ['url', 'alt_text', 'position'];
        if (array_diff(array_keys($request->all()), $allowed) !== []) {
            abort(422, 'The request contains fields that cannot be edited.');
        }
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'url' => [$required, 'url:http,https', 'max:2048'],
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ]);

        if ($partial && $data === []) {
            throw ValidationException::withMessages(['image' => 'At least one image field must be provided.']);
        }

        return $data;
    }
}
