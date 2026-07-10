<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorProductApprovalStatus;
use App\Enums\VendorStatus;
use App\Models\OrderItem;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminVendorOverviewService
{
    public function __construct(
        private readonly AdminMarketplaceAnalyticsService $analytics,
        private readonly AdminVendorListService $list
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $overview = $this->analytics->overview();

        return [
            'kpis' => $this->kpis($overview),
            'charts' => $this->charts(),
            'recent' => $this->recentActivity(),
            'top_vendors' => $this->analytics->topVendorsByRevenue(10),
        ];
    }

    /**
     * @param  array<string, mixed>  $overview
     * @return list<array<string, mixed>>
     */
    private function kpis(array $overview): array
    {
        $v = $overview['vendors'];
        $p = $overview['products'];
        $o = $overview['orders'];
        $r = $overview['revenue'];
        $inv = $overview['inventory'];

        $activeProducts = VendorProduct::query()
            ->where('status', 'active')
            ->where('approval_status', VendorProductApprovalStatus::Approved->value)
            ->where('disabled_by_admin', false)
            ->count();

        $disabledProducts = VendorProduct::query()->where('disabled_by_admin', true)->count();
        $verifiedVendors = $this->countVerifiedVendors();
        $processingOrders = VendorOrderMapping::query()->whereIn('status', [
            VendorOrderStatus::Confirmed->value,
            VendorOrderStatus::Processing->value,
            VendorOrderStatus::Shipped->value,
        ])->count();

        $pendingVendors = ($v['pending'] ?? 0) + Vendor::where('status', VendorStatus::UnderReview->value)->count();

        return [
            $this->kpi('Total Vendors', $v['total'], 'All registered vendors', 'users', $this->trend(fn () => Vendor::where('created_at', '>=', now()->subDays(30))->count(), fn () => Vendor::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count())),
            $this->kpi('Active Vendors', $v['approved'], 'Currently approved', 'check', $this->trend(fn () => $v['approved'], fn () => $v['approved'])),
            $this->kpi('Pending Vendors', $pendingVendors, 'Awaiting review', 'clock', $this->trend(fn () => $pendingVendors, fn () => $pendingVendors)),
            $this->kpi('Suspended Vendors', ($v['suspended'] ?? 0) + ($v['rejected'] ?? 0), 'Suspended or rejected', 'ban', null),
            $this->kpi('Verified Vendors', $verifiedVendors, 'Fully verified documents', 'shield', null),
            $this->kpi('Total Products', $p['total'], 'Vendor catalog listings', 'box', $this->trend(fn () => VendorProduct::where('created_at', '>=', now()->subDays(30))->count(), fn () => VendorProduct::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count())),
            $this->kpi('Active Products', $activeProducts, 'Live on marketplace', 'sparkles', null, 'text-emerald-600'),
            $this->kpi('Pending Products', $p['pending_approval'], 'Awaiting approval', 'hourglass', null, 'text-amber-600'),
            $this->kpi('Out of Stock', $inv['out_of_stock'], 'Needs restocking', 'alert', null, 'text-rose-600'),
            $this->kpi('Total Orders', $o['total'], 'All vendor orders', 'cart', $this->trend(fn () => VendorOrderMapping::where('created_at', '>=', now()->subDays(30))->count(), fn () => VendorOrderMapping::whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])->count())),
            $this->kpi('Pending Orders', $o['pending'], 'Need fulfillment', 'inbox', null, 'text-amber-600'),
            $this->kpi('Completed Orders', $o['completed'], 'Successfully delivered', 'truck', null, 'text-emerald-600'),
            $this->kpi('Processing Orders', $processingOrders, 'In progress', 'cog', null, 'text-sky-600'),
            $this->kpi('Cancelled Orders', $o['cancelled'], 'Cancelled orders', 'x', null, 'text-gray-600'),
            $this->kpi('Total Revenue', 'AED '.number_format($r['gross'], 2), 'Gross marketplace revenue', 'currency', $this->trendRevenue(), 'text-indigo-600'),
            $this->kpi('Vendor Earnings', 'AED '.number_format($r['vendor_payout_estimate'], 2), 'Estimated vendor share', 'wallet', null, 'text-emerald-600'),
            $this->kpi('Platform Commission', 'AED '.number_format($r['platform_commission'], 2), 'Platform earnings', 'chart', null, 'text-violet-600'),
            $this->kpi('Disabled Products', $disabledProducts, 'Disabled by admin', 'lock', null, 'text-rose-600'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function charts(): array
    {
        return [
            'revenue_growth' => $this->monthlySeries('revenue', 6),
            'orders' => $this->monthlySeries('orders', 6),
            'vendor_registrations' => $this->monthlyVendorRegistrations(6),
            'product_growth' => $this->monthlyProductGrowth(6),
            'order_status' => $this->orderStatusDistribution(),
            'revenue_vs_commission' => $this->monthlyRevenueCommission(6),
            'top_products' => $this->topSellingProducts(8),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recentActivity(): array
    {
        return [
            'vendors' => Vendor::with('profile')->latest()->limit(6)->get(),
            'pending_approvals' => Vendor::with('profile')
                ->whereIn('status', [VendorStatus::Pending->value, VendorStatus::UnderReview->value])
                ->latest()
                ->limit(6)
                ->get(),
            'products' => VendorProduct::with(['product', 'vendor.profile'])->latest()->limit(6)->get(),
            'orders' => VendorOrderMapping::with(['order.user', 'vendor.profile'])->latest()->limit(6)->get(),
            'completed_orders' => VendorOrderMapping::with(['order.user', 'vendor.profile'])
                ->where('status', VendorOrderStatus::Delivered->value)
                ->latest()
                ->limit(6)
                ->get(),
            'low_stock' => VendorProduct::with(['product', 'vendor.profile', 'inventory'])
                ->whereHas('inventory', fn ($q) => $q->whereColumn('quantity', '<=', 'low_stock_threshold')->where('quantity', '>', 0))
                ->limit(6)
                ->get(),
            'disabled_products' => VendorProduct::with(['product', 'vendor.profile'])
                ->where('disabled_by_admin', true)
                ->latest('disabled_by_admin_at')
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * @return list<array{month: string, value: float|int}>
     */
    private function monthlySeries(string $type, int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $q = VendorOrderMapping::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '!=', VendorOrderStatus::Cancelled->value);
            $value = $type === 'orders'
                ? (int) (clone $q)->count()
                : round((float) (clone $q)->sum('total_amount'), 2);
            $series[] = ['month' => $start->format('M Y'), 'value' => $value];
        }

        return $series;
    }

    /**
     * @return list<array{month: string, value: int}>
     */
    private function monthlyVendorRegistrations(int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $series[] = [
                'month' => $start->format('M Y'),
                'value' => Vendor::whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{month: string, value: int}>
     */
    private function monthlyProductGrowth(int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $series[] = [
                'month' => $start->format('M Y'),
                'value' => VendorProduct::whereBetween('created_at', [$start, $end])->count(),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{month: string, revenue: float, commission: float}>
     */
    private function monthlyRevenueCommission(int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $q = VendorOrderMapping::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '!=', VendorOrderStatus::Cancelled->value);
            $series[] = [
                'month' => $start->format('M Y'),
                'revenue' => round((float) (clone $q)->sum('total_amount'), 2),
                'commission' => round((float) (clone $q)->sum('commission_amount'), 2),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{status: string, count: int}>
     */
    private function orderStatusDistribution(): array
    {
        return VendorOrderMapping::query()
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => ['status' => ucfirst(str_replace('_', ' ', $row->status)), 'count' => (int) $row->count])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topSellingProducts(int $limit): array
    {
        return OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(subtotal) as revenue'))
            ->whereHas('order.vendorMappings')
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $vp = VendorProduct::with(['product', 'vendor.profile'])->where('product_id', $row->product_id)->first();

                return [
                    'name' => $vp?->product?->name ?? 'Product #'.$row->product_id,
                    'vendor' => $vp?->vendor?->profile?->business_name ?? '—',
                    'units_sold' => (int) $row->units_sold,
                    'revenue' => round((float) $row->revenue, 2),
                ];
            })
            ->all();
    }

    private function countVerifiedVendors(): int
    {
        return Vendor::with('documents')->get()->filter(fn (Vendor $v) => $this->list->isVerified($v))->count();
    }

    /**
     * @return array{change: float, direction: string}|null
     */
    private function trend(callable $currentFn, callable $previousFn): ?array
    {
        $current = (float) $currentFn();
        $previous = (float) $previousFn();
        if ($previous <= 0 && $current <= 0) {
            return null;
        }
        $change = $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 100;

        return [
            'change' => abs($change),
            'direction' => $change >= 0 ? 'up' : 'down',
        ];
    }

    /**
     * @return array{change: float, direction: string}|null
     */
    private function trendRevenue(): ?array
    {
        $current = (float) VendorOrderMapping::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->where('status', '!=', VendorOrderStatus::Cancelled->value)
            ->sum('total_amount');
        $previous = (float) VendorOrderMapping::query()
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(30)])
            ->where('status', '!=', VendorOrderStatus::Cancelled->value)
            ->sum('total_amount');

        return $this->trend(fn () => $current, fn () => $previous);
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $label, mixed $value, string $hint, string $icon, ?array $trend, string $accent = 'text-gray-900 dark:text-gray-100'): array
    {
        return compact('label', 'value', 'hint', 'icon', 'trend', 'accent');
    }
}
