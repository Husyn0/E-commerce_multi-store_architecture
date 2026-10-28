<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends TenantApiController
{
    public function updateProduct(
        Request $request,
        string $productId,
        AuditLogger $auditLogger,
    ) {
        $data = $request->validate(['stock' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($auditLogger, $data, $productId, $request) {
            $product = Product::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->findOrFail($productId);

            if ($product->variants()->exists()) {
                return response()->json([
                    'message' => 'Manage stock through product variants for this product.',
                ], 422);
            }

            if ((int) $product->stock === $data['stock']) {
                return response()->json(['product_id' => $product->id, 'stock' => $product->stock]);
            }

            $previousStock = (int) $product->stock;
            $product->stock = $data['stock'];
            $product->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                'inventory.adjusted',
                'product',
                $product->id,
                $request,
                ['previous_stock' => $previousStock, 'stock' => (int) $product->stock],
            );

            return response()->json(['product_id' => $product->id, 'stock' => (int) $product->stock]);
        });
    }

    public function updateVariant(
        Request $request,
        string $productId,
        string $variantId,
        AuditLogger $auditLogger,
    ) {
        $data = $request->validate(['stock' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($auditLogger, $data, $productId, $request, $variantId) {
            $product = Product::query()->forTenant($this->tenantId())->findOrFail($productId);
            $variant = ProductVariant::query()
                ->forTenant($this->tenantId())
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->findOrFail($variantId);

            if ((int) $variant->stock !== $data['stock']) {
                $previousStock = (int) $variant->stock;
                $variant->stock = $data['stock'];
                $variant->save();
                $auditLogger->web(
                    $this->tenantContext(),
                    $request->user(),
                    'inventory.adjusted',
                    'product_variant',
                    $variant->id,
                    $request,
                    ['previous_stock' => $previousStock, 'stock' => (int) $variant->stock],
                );
            }

            return response()->json([
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'stock' => (int) $variant->stock,
            ]);
        });
    }
}
