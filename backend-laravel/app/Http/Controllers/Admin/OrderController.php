<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TenantSettings;
use App\Services\AuditLogger;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends TenantApiController
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'in:pending,processing,shipped,delivered,cancelled'],
            'payment_status' => ['sometimes', 'in:requires_payment,processing,succeeded,failed,refunded'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->forTenant($this->tenantId())
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['payment_status']), fn ($query) => $query->where('payment_status', $filters['payment_status']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 25);

        return OrderResource::collection($orders);
    }

    public function show(string $orderId, Request $request, AuditLogger $auditLogger): OrderResource
    {
        $order = Order::query()
            ->forTenant($this->tenantId())
            ->with(['items', 'refunds'])
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->findOrFail($orderId);

        $auditLogger->web(
            $this->tenantContext(),
            $request->user(),
            'order.read',
            'order',
            $order->id,
            $request,
        );

        return new OrderResource($order);
    }

    public function updateStatus(
        Request $request,
        string $orderId,
        AuditLogger $auditLogger,
        PaymentGatewayManager $gateways,
    ): OrderResource|JsonResponse {
        $data = $request->validate([
            'status' => ['required', 'in:pending,processing,shipped,delivered,cancelled'],
        ]);
        $paymentCancelled = false;
        if ($data['status'] === 'cancelled') {
            $currentOrder = Order::query()->forTenant($this->tenantId())->findOrFail($orderId);
            if (in_array($currentOrder->payment_status, ['succeeded', 'refunded'], true)) {
                return response()->json(['message' => 'A paid order must be refunded before it can be cancelled.'], 409);
            }
            if ($currentOrder->payment_intent_id !== null) {
                $settings = TenantSettings::query()->forTenant($this->tenantId())->first();
                if ($settings === null) {
                    return response()->json(['message' => 'Payment cancellation is not configured.'], 503);
                }
                if ($settings->payment_driver !== $currentOrder->payment_provider) {
                    return response()->json([
                        'message' => 'The payment configuration changed; restore the order provider before cancelling.',
                    ], 503);
                }
                try {
                    $gateway = $gateways->forTenant($settings);
                    $gateway->cancelIntent($settings, $currentOrder, 'cancel_order_'.$currentOrder->id);
                    $paymentCancelled = true;
                } catch (PaymentProviderException $exception) {
                    report($exception);

                    return response()->json([
                        'message' => 'The payment must be confirmed as cancelled before cancelling this order.',
                    ], 409);
                }
            }
        }

        $result = DB::transaction(function () use ($auditLogger, $data, $orderId, $paymentCancelled, $request): Order|JsonResponse {
            $order = Order::query()
                ->forTenant($this->tenantId())
                ->lockForUpdate()
                ->findOrFail($orderId);

            if ($order->status === $data['status']) {
                return $order;
            }

            if (! $this->isAllowedTransition($order->status, $data['status'])) {
                return response()->json(['message' => 'This order status transition is not allowed.'], 409);
            }

            if ($data['status'] === 'cancelled'
                && in_array($order->payment_status, ['succeeded', 'refunded'], true)) {
                return response()->json([
                    'message' => 'A paid order must be refunded before it can be cancelled.',
                ], 409);
            }

            $order->status = $data['status'];
            if ($data['status'] === 'cancelled') {
                if ($order->payment_intent_id !== null && ! $paymentCancelled) {
                    return response()->json(['message' => 'The payment must be cancelled before cancelling this order.'], 409);
                }
                $order->payment_status = 'failed';
                $this->restoreInventory($order);
            }
            $order->save();
            $auditLogger->web(
                $this->tenantContext(),
                $request->user(),
                $order->status === 'cancelled' ? 'order.cancelled' : 'order.updated',
                'order',
                $order->id,
                $request,
                ['status' => $order->status],
            );

            return $order;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return new OrderResource($result->load('items')->loadSum(
            ['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')],
            'amount_cents',
        ));
    }

    private function isAllowedTransition(string $current, string $next): bool
    {
        return in_array($next, match ($current) {
            'pending' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered'],
            default => [],
        }, true);
    }

    private function restoreInventory(Order $order): void
    {
        $items = OrderItem::query()
            ->forTenant($order->tenant_id)
            ->where('order_id', $order->id)
            ->lockForUpdate()
            ->get();

        foreach ($items as $item) {
            if ($item->variant_id !== null) {
                $variant = ProductVariant::query()
                    ->forTenant($order->tenant_id)
                    ->whereKey($item->variant_id)
                    ->lockForUpdate()
                    ->first();
                if ($variant !== null) {
                    $variant->stock = (int) $variant->stock + (int) $item->quantity;
                    $variant->save();
                }

                continue;
            }

            if ($item->product_id !== null) {
                $product = Product::query()
                    ->forTenant($order->tenant_id)
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();
                if ($product !== null) {
                    $product->stock = (int) $product->stock + (int) $item->quantity;
                    $product->save();
                }
            }
        }
    }
}
