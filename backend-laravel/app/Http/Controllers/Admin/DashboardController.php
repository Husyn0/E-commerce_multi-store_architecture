<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\TenantApiController;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends TenantApiController
{
    public function __invoke(): JsonResponse
    {
        $now = Carbon::now('UTC');
        $from = $now->copy()->startOfDay()->subDays(6);
        $paidOrders = Order::query()
            ->forTenant($this->tenantId())
            ->where('payment_status', 'succeeded')
            ->where('status', '!=', 'cancelled')
            ->whereBetween('paid_at', [$from, $now]);

        $salesByDay = (clone $paidOrders)
            ->selectRaw('DATE(paid_at) AS date')
            ->selectRaw('SUM(total_cents) AS total_cents')
            ->selectRaw('COUNT(*) AS order_count')
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $days = [];
        for ($date = $from->copy()->startOfDay(); $date <= $now; $date->addDay()) {
            $key = $date->toDateString();
            $day = $salesByDay->get($key);
            $days[] = [
                'date' => $key,
                'total_cents' => (int) ($day?->total_cents ?? 0),
                'order_count' => (int) ($day?->order_count ?? 0),
            ];
        }

        $recentOrders = Order::query()
            ->forTenant($this->tenantId())
            ->withSum(['refunds as refunded_total_cents' => fn ($query) => $query->where('status', 'succeeded')], 'amount_cents')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pendingFulfillment = Order::query()
            ->forTenant($this->tenantId())
            ->where('payment_status', 'succeeded')
            ->where('status', '!=', 'cancelled')
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return response()->json([
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $now->toIso8601String(),
                'timezone' => 'UTC',
                'currency' => $this->tenantContext()->tenant->default_currency,
            ],
            'sales' => [
                'total_cents' => (int) (clone $paidOrders)->sum('total_cents'),
                'order_count' => (int) (clone $paidOrders)->count(),
                'by_day' => $days,
            ],
            'pending_fulfillment_count' => $pendingFulfillment,
            'recent_orders' => OrderResource::collection($recentOrders),
        ])->header('Cache-Control', 'no-store');
    }
}
