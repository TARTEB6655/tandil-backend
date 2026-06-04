<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Illuminate\Support\Facades\DB;

class VendorDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(Vendor $vendor): array
    {
        $productIds = VendorProduct::where('vendor_id', $vendor->id)->pluck('product_id');

        $activeProducts = VendorProduct::where('vendor_id', $vendor->id)->where('status', 'active')->count();
        $totalProducts = VendorProduct::where('vendor_id', $vendor->id)->count();

        $inventory = VendorInventory::whereHas('vendorProduct', fn ($q) => $q->where('vendor_id', $vendor->id))->get();
        $outOfStock = $inventory->filter(fn ($i) => $i->isOutOfStock())->count();
        $lowStock = $inventory->filter(fn ($i) => $i->isLowStock())->count();

        $ordersQuery = VendorOrderMapping::where('vendor_id', $vendor->id);
        $totalOrders = (clone $ordersQuery)->count();
        $pendingOrders = (clone $ordersQuery)->where('status', VendorOrderStatus::Pending->value)->count();
        $completedOrders = (clone $ordersQuery)->where('status', VendorOrderStatus::Delivered->value)->count();

        $revenue = (float) (clone $ordersQuery)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('total_amount');

        $avgOrder = $totalOrders > 0 ? round($revenue / $totalOrders, 2) : 0;

        return [
            'total_products' => $totalProducts,
            'active_products' => $activeProducts,
            'out_of_stock_products' => $outOfStock,
            'low_stock_products' => $lowStock,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'completed_orders' => $completedOrders,
            'revenue' => round($revenue, 2),
            'average_order_value' => $avgOrder,
            'recent_orders' => VendorOrderMapping::with(['order.user'])
                ->where('vendor_id', $vendor->id)
                ->latest()
                ->limit(5)
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
