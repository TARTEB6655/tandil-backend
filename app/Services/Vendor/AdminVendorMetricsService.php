<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminVendorMetricsService
{
    /**
     * Lightweight KPIs for vendor list rows (batch-loaded).
     *
     * @param  list<int>  $vendorIds
     * @return array<int, array<string, int|float>>
     */
    public function mapForVendorIds(array $vendorIds): array
    {
        $vendorIds = array_values(array_unique(array_filter($vendorIds)));
        if ($vendorIds === []) {
            return [];
        }

        $products = VendorProduct::query()
            ->whereIn('vendor_id', $vendorIds)
            ->select('vendor_id')
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products")
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $cancelled = VendorOrderStatus::Cancelled->value;
        $pending = VendorOrderStatus::Pending->value;

        $orders = VendorOrderMapping::query()
            ->whereIn('vendor_id', $vendorIds)
            ->select('vendor_id')
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_orders', [$pending])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 0 ELSE total_amount END) as revenue', [$cancelled])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 0 ELSE commission_amount END) as commission_earned', [$cancelled])
            ->groupBy('vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $lowStock = VendorInventory::query()
            ->join('vendor_products', 'vendor_inventory.vendor_product_id', '=', 'vendor_products.id')
            ->whereIn('vendor_products.vendor_id', $vendorIds)
            ->whereColumn('vendor_inventory.quantity', '<=', 'vendor_inventory.low_stock_threshold')
            ->select('vendor_products.vendor_id')
            ->selectRaw('COUNT(*) as low_stock_products')
            ->groupBy('vendor_products.vendor_id')
            ->get()
            ->keyBy('vendor_id');

        $map = [];
        foreach ($vendorIds as $vendorId) {
            $map[$vendorId] = $this->formatRow(
                $products->get($vendorId),
                $orders->get($vendorId),
                $lowStock->get($vendorId)
            );
        }

        return $map;
    }

    /**
     * @return array<string, int|float>
     */
    public function forVendor(Vendor $vendor): array
    {
        return $this->mapForVendorIds([$vendor->id])[$vendor->id] ?? $this->emptyMetrics();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatListItem(Vendor $vendor, ?array $metrics = null): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;

        return [
            'id' => $vendor->id,
            'status' => $vendor->status,
            'status_label' => $vendor->statusEnum()->label(),
            'commission_rate' => $vendor->commission_rate !== null ? (float) $vendor->commission_rate : null,
            'approved_at' => $vendor->approved_at?->toIso8601String(),
            'created_at' => $vendor->created_at?->toIso8601String(),
            'business_name' => $profile?->business_name,
            'owner_name' => $profile?->owner_name,
            'email' => $profile?->email ?? $vendor->user?->email,
            'phone' => $profile?->phone ?? $vendor->user?->phone,
            'logo_url' => $profile?->logo_url,
            'emirate' => $profile?->emirate,
            'city' => $profile?->city,
            'metrics' => $metrics ?? $this->emptyMetrics(),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function formatRow(mixed $productRow, mixed $orderRow, mixed $lowStockRow): array
    {
        return [
            'total_products' => (int) ($productRow->total_products ?? 0),
            'active_products' => (int) ($productRow->active_products ?? 0),
            'low_stock_products' => (int) ($lowStockRow->low_stock_products ?? 0),
            'total_orders' => (int) ($orderRow->total_orders ?? 0),
            'pending_orders' => (int) ($orderRow->pending_orders ?? 0),
            'revenue' => round((float) ($orderRow->revenue ?? 0), 2),
            'commission_earned' => round((float) ($orderRow->commission_earned ?? 0), 2),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    public function emptyMetricsPublic(): array
    {
        return $this->emptyMetrics();
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyMetrics(): array
    {
        return [
            'total_products' => 0,
            'active_products' => 0,
            'low_stock_products' => 0,
            'total_orders' => 0,
            'pending_orders' => 0,
            'revenue' => 0.0,
            'commission_earned' => 0.0,
        ];
    }
}
