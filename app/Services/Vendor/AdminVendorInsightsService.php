<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminVendorInsightsService
{
    public function __construct(
        private readonly AdminMarketplaceAnalyticsService $marketplace,
        private readonly AdminVendorMetricsService $metrics
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $overview = $this->marketplace->overview();

        return [
            'overview' => $overview,
            'top_selling_vendors' => $this->marketplace->topVendorsByRevenue(10),
            'highest_revenue' => $this->marketplace->topVendorsByRevenue(5),
            'lowest_performing' => $this->lowestPerformingVendors(5),
            'new_vendors' => $this->newVendors(8),
            'best_products' => $this->bestSellingProducts(8),
            'revenue_growth' => $this->growthSeries('revenue', 6),
            'order_growth' => $this->growthSeries('orders', 6),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lowestPerformingVendors(int $limit): array
    {
        return VendorOrderMapping::query()
            ->select('vendor_id', DB::raw('SUM(total_amount) as revenue'), DB::raw('COUNT(*) as order_count'))
            ->whereNotIn('status', ['cancelled'])
            ->groupBy('vendor_id')
            ->orderBy('revenue')
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

    /**
     * @return list<array<string, mixed>>
     */
    private function newVendors(int $limit): array
    {
        return Vendor::with('profile')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Vendor $v) => [
                'vendor_id' => $v->id,
                'business_name' => $v->profile?->business_name,
                'status' => $v->statusEnum()->label(),
                'created_at' => $v->created_at?->format('M j, Y'),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bestSellingProducts(int $limit): array
    {
        return VendorProduct::query()
            ->with(['product', 'vendor.profile'])
            ->where('approval_status', 'approved')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($vp) => [
                'product_id' => $vp->product_id,
                'name' => $vp->product?->name,
                'vendor_name' => $vp->vendor?->profile?->business_name,
                'status' => $vp->status,
            ])
            ->all();
    }

    /**
     * @return list<array{month: string, value: float|int}>
     */
    private function growthSeries(string $type, int $months): array
    {
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $label = $start->format('M Y');

            $query = VendorOrderMapping::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '!=', 'cancelled');

            $value = $type === 'orders'
                ? (int) (clone $query)->count()
                : round((float) (clone $query)->sum('total_amount'), 2);

            $series[] = ['month' => $label, 'value' => $value];
        }

        return $series;
    }
}
