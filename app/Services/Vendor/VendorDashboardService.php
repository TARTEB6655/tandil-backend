<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendorDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(Vendor $vendor): array
    {
        return array_merge($this->stats($vendor), [
            'analytics' => $this->analytics($vendor),
        ]);
    }

    /**
     * Lightweight counts for the vendor profile screen (avoid full stats() work).
     *
     * @return array{total_products: int, completed_orders: int}
     */
    public function profileSummaryStats(Vendor $vendor): array
    {
        return [
            'total_products' => VendorProduct::where('vendor_id', $vendor->id)->count(),
            'completed_orders' => VendorOrderMapping::where('vendor_id', $vendor->id)
                ->where('status', VendorOrderStatus::Delivered->value)
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(Vendor $vendor): array
    {
        $activeProducts = VendorProduct::where('vendor_id', $vendor->id)->where('status', 'active')->count();
        $disabledProducts = VendorProduct::where('vendor_id', $vendor->id)->where('disabled_by_admin', true)->count();
        $totalProducts = VendorProduct::where('vendor_id', $vendor->id)->count();

        $inventory = VendorInventory::whereHas('vendorProduct', fn ($q) => $q->where('vendor_id', $vendor->id))->get();
        $outOfStock = $inventory->filter(fn ($i) => $i->isOutOfStock())->count();
        $lowStock = $inventory->filter(fn ($i) => $i->isLowStock())->count();

        $ordersQuery = VendorOrderMapping::where('vendor_id', $vendor->id);
        $totalOrders = (clone $ordersQuery)->count();
        $pendingOrders = (clone $ordersQuery)->where('status', VendorOrderStatus::Pending->value)->count();
        $completedOrders = (clone $ordersQuery)->where('status', VendorOrderStatus::Delivered->value)->count();
        $processingOrders = (clone $ordersQuery)->whereIn('status', [
            VendorOrderStatus::Confirmed->value,
            VendorOrderStatus::Processing->value,
            VendorOrderStatus::Shipped->value,
        ])->count();

        $revenue = (float) (clone $ordersQuery)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('total_amount');

        $commission = (float) (clone $ordersQuery)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('commission_amount');

        $avgOrder = $totalOrders > 0 ? round($revenue / $totalOrders, 2) : 0;

        $uniqueCustomers = (int) VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->whereHas('order', fn ($q) => $q->whereNotNull('user_id'))
            ->join('orders', 'vendor_order_mappings.order_id', '=', 'orders.id')
            ->distinct('orders.user_id')
            ->count('orders.user_id');

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'disabled_products' => $disabledProducts,
            'out_of_stock_products' => $outOfStock,
            'low_stock_products' => $lowStock,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'processing_orders' => $processingOrders,
            'completed_orders' => $completedOrders,
            'revenue' => round($revenue, 2),
            'commission_paid' => round($commission, 2),
            'net_earnings' => round(max(0, $revenue - $commission), 2),
            'average_order_value' => $avgOrder,
            'unique_customers' => $uniqueCustomers,
            'recent_orders' => VendorOrderMapping::with(['order.user'])
                ->where('vendor_id', $vendor->id)
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn ($m) => $this->orderRow($m)),
            'inventory_alerts' => VendorProduct::with(['product', 'inventory'])
                ->where('vendor_id', $vendor->id)
                ->whereHas('inventory', function ($q) {
                    $q->whereColumn('quantity', '<=', 'low_stock_threshold');
                })
                ->limit(10)
                ->get()
                ->map(fn ($vp) => [
                    'vendor_product_id' => $vp->id,
                    'product_name' => $vp->product?->name,
                    'quantity' => $vp->inventory?->quantity ?? 0,
                    'low_stock_threshold' => $vp->inventory?->low_stock_threshold ?? 0,
                ]),
        ];
    }

    /**
     * Fast counts for mobile/web dashboard summary cards (no charts or recent tables).
     *
     * @return array<string, mixed>
     */
    public function dashboardSummaryMetrics(Vendor $vendor): array
    {
        $vendorId = $vendor->id;
        $cancelled = VendorOrderStatus::Cancelled->value;
        $pending = VendorOrderStatus::Pending->value;
        $delivered = VendorOrderStatus::Delivered->value;

        $productRow = VendorProduct::query()
            ->where('vendor_id', $vendorId)
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products")
            ->first();

        $lowStock = (int) VendorInventory::query()
            ->whereHas('vendorProduct', fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        $orderRow = VendorOrderMapping::query()
            ->where('vendor_id', $vendorId)
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_orders', [$pending])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as delivered_orders', [$delivered])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 0 ELSE total_amount END) as revenue', [$cancelled])
            ->first();

        return [
            'currency' => 'AED',
            'revenue' => round((float) ($orderRow->revenue ?? 0), 2),
            'pending_orders' => (int) ($orderRow->pending_orders ?? 0),
            'products' => (int) ($productRow->total_products ?? 0),
            'active' => (int) ($productRow->active_products ?? 0),
            'low_stock' => $lowStock,
            'total_orders' => (int) ($orderRow->total_orders ?? 0),
            'delivered_orders' => (int) ($orderRow->delivered_orders ?? 0),
        ];
    }

    /**
     * Lightweight mobile home-screen payload (matches Vendor Portal dashboard cards).
     *
     * @return array<string, mixed>
     */
    public function mobileSummary(Vendor $vendor): array
    {
        return $this->dashboardSummaryMetrics($vendor);
    }

    /**
     * Chart and trend data for vendor dashboard (also exposed via API).
     *
     * @return array<string, mixed>
     */
    public function analytics(Vendor $vendor): array
    {
        $vendorId = $vendor->id;
        $cancelled = VendorOrderStatus::Cancelled->value;

        $ordersByStatus = VendorOrderMapping::query()
            ->where('vendor_id', $vendorId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $monthly = $this->monthlySeries($vendorId, $cancelled, 6);

        $productIds = VendorProduct::where('vendor_id', $vendorId)->pluck('product_id');

        $topProducts = collect();
        if ($productIds->isNotEmpty()) {
            $topProducts = OrderItem::query()
                ->select('product_id', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(subtotal) as revenue'))
                ->whereIn('product_id', $productIds)
                ->whereHas('order.vendorMappings', fn ($q) => $q->where('vendor_id', $vendorId))
                ->groupBy('product_id')
                ->orderByDesc('revenue')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    $product = \App\Models\Product::find($row->product_id);

                    return [
                        'product_id' => $row->product_id,
                        'name' => $product?->name ?? 'Product #'.$row->product_id,
                        'units_sold' => (int) $row->units_sold,
                        'revenue' => round((float) $row->revenue, 2),
                    ];
                });
        }

        $customerGrowth = $this->customerGrowthSeries($vendorId, 6);

        return [
            'orders_by_status' => $ordersByStatus,
            'monthly_revenue' => $monthly['revenue'],
            'monthly_orders' => $monthly['orders'],
            'monthly_earnings' => $monthly['earnings'],
            'top_products' => $topProducts->values()->all(),
            'customer_growth' => $customerGrowth,
            'sales_overview' => [
                'labels' => $monthly['labels'],
                'revenue' => array_column($monthly['revenue'], 'amount'),
                'orders' => array_column($monthly['orders'], 'count'),
            ],
        ];
    }

    /**
     * @return array{labels: list<string>, revenue: list<array{month: string, amount: float}>, orders: list<array{month: string, count: int}>, earnings: list<array{month: string, amount: float}>}
     */
    private function monthlySeries(int $vendorId, string $cancelled, int $months): array
    {
        $labels = [];
        $revenue = [];
        $orders = [];
        $earnings = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $label = $start->format('M Y');

            $base = VendorOrderMapping::query()
                ->where('vendor_id', $vendorId)
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '!=', $cancelled);

            $rev = (float) (clone $base)->sum('total_amount');
            $comm = (float) (clone $base)->sum('commission_amount');
            $count = (int) (clone $base)->count();

            $labels[] = $label;
            $revenue[] = ['month' => $label, 'amount' => round($rev, 2)];
            $orders[] = ['month' => $label, 'count' => $count];
            $earnings[] = ['month' => $label, 'amount' => round(max(0, $rev - $comm), 2)];
        }

        return compact('labels', 'revenue', 'orders', 'earnings');
    }

    /**
     * @return list<array{month: string, count: int}>
     */
    private function customerGrowthSeries(int $vendorId, int $months): array
    {
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $label = $start->format('M Y');

            $count = (int) DB::table('vendor_order_mappings')
                ->join('orders', 'vendor_order_mappings.order_id', '=', 'orders.id')
                ->where('vendor_order_mappings.vendor_id', $vendorId)
                ->whereNotNull('orders.user_id')
                ->whereBetween('vendor_order_mappings.created_at', [$start, $end])
                ->distinct('orders.user_id')
                ->count('orders.user_id');

            $series[] = ['month' => $label, 'count' => $count];
        }

        return $series;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(VendorOrderMapping $mapping): array
    {
        return [
            'id' => $mapping->id,
            'order_id' => $mapping->order_id,
            'status' => $mapping->status,
            'total_amount' => (float) $mapping->total_amount,
            'created_at' => $mapping->created_at?->toIso8601String(),
            'customer_name' => $mapping->order?->user?->name ?? $mapping->order?->guest_full_name,
        ];
    }
}
