<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorProductApprovalStatus;
use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\DB;

class AdminMarketplaceAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $vendorOrders = VendorOrderMapping::query();
        $revenue = (float) (clone $vendorOrders)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('total_amount');
        $commission = (float) (clone $vendorOrders)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('commission_amount');

        $inventory = VendorInventory::query()->get();
        $lowStock = $inventory->filter(fn ($i) => $i->quantity > 0 && $i->quantity <= $i->low_stock_threshold)->count();
        $outOfStock = $inventory->filter(fn ($i) => $i->quantity <= 0)->count();

        return [
            'vendors' => [
                'total' => Vendor::count(),
                'pending' => Vendor::where('status', VendorStatus::Pending->value)->count(),
                'approved' => Vendor::where('status', VendorStatus::Approved->value)->count(),
                'suspended' => Vendor::where('status', VendorStatus::Suspended->value)->count(),
                'rejected' => Vendor::where('status', VendorStatus::Rejected->value)->count(),
            ],
            'products' => [
                'total' => VendorProduct::count(),
                'pending_approval' => VendorProduct::where('approval_status', VendorProductApprovalStatus::Pending->value)->count(),
                'approved' => VendorProduct::where('approval_status', VendorProductApprovalStatus::Approved->value)->count(),
                'rejected' => VendorProduct::where('approval_status', VendorProductApprovalStatus::Rejected->value)->count(),
            ],
            'orders' => [
                'total' => VendorOrderMapping::count(),
                'pending' => VendorOrderMapping::where('status', VendorOrderStatus::Pending->value)->count(),
                'completed' => VendorOrderMapping::where('status', VendorOrderStatus::Delivered->value)->count(),
                'cancelled' => VendorOrderMapping::where('status', VendorOrderStatus::Cancelled->value)->count(),
                'open_disputes' => VendorOrderMapping::whereIn('dispute_status', ['open', 'under_review'])->count(),
            ],
            'revenue' => [
                'gross' => round($revenue, 2),
                'platform_commission' => round($commission, 2),
                'vendor_payout_estimate' => round($revenue - $commission, 2),
            ],
            'inventory' => [
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
            'settings' => [
                'commission_percent' => MarketplaceSettings::commissionPercent(),
                'product_approval_required' => MarketplaceSettings::productApprovalRequired(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topVendorsByRevenue(int $limit = 10): array
    {
        return VendorOrderMapping::query()
            ->select('vendor_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as order_count'))
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->groupBy('vendor_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->with('vendor.profile')
            ->get()
            ->map(fn ($row) => [
                'vendor_id' => $row->vendor_id,
                'business_name' => $row->vendor?->profile?->business_name,
                'revenue' => round((float) $row->revenue, 2),
                'order_count' => (int) $row->order_count,
            ])
            ->all();
    }
}
