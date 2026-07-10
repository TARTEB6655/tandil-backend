<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminVendorRevenueService
{
    /**
     * Platform-wide revenue overview.
     *
     * @return array<string, mixed>
     */
    public function platformOverview(): array
    {
        $base = VendorOrderMapping::query()
            ->where('status', '!=', VendorOrderStatus::Cancelled->value);

        $gross = (float) (clone $base)->sum('total_amount');
        $commission = (float) (clone $base)->sum('commission_amount');
        $vendorEarnings = max(0, $gross - $commission);

        $pendingPayout = (float) VendorOrderMapping::query()
            ->whereIn('status', [
                VendorOrderStatus::Delivered->value,
                VendorOrderStatus::Shipped->value,
            ])
            ->sum(DB::raw('total_amount - commission_amount'));

        return [
            'total_revenue' => round($gross, 2),
            'vendor_earnings' => round($vendorEarnings, 2),
            'platform_earnings' => round($commission, 2),
            'commission' => round($commission, 2),
            'pending_payments' => round($pendingPayout, 2),
            'withdrawals' => 0.0,
            'monthly' => $this->monthlySeries(null, 6),
        ];
    }

    /**
     * Per-vendor revenue breakdown.
     *
     * @return array<string, mixed>
     */
    public function forVendor(Vendor $vendor): array
    {
        $vendorId = $vendor->id;
        $cancelled = VendorOrderStatus::Cancelled->value;

        $base = VendorOrderMapping::query()
            ->where('vendor_id', $vendorId)
            ->where('status', '!=', $cancelled);

        $gross = (float) (clone $base)->sum('total_amount');
        $commission = (float) (clone $base)->sum('commission_amount');

        return [
            'total_revenue' => round($gross, 2),
            'vendor_earnings' => round(max(0, $gross - $commission), 2),
            'platform_earnings' => round($commission, 2),
            'commission' => round($commission, 2),
            'wallet_balance' => round(max(0, $gross - $commission), 2),
            'pending_payments' => round((float) VendorOrderMapping::query()
                ->where('vendor_id', $vendorId)
                ->whereIn('status', [VendorOrderStatus::Delivered->value, VendorOrderStatus::Shipped->value])
                ->sum(DB::raw('total_amount - commission_amount')), 2),
            'withdrawals' => 0.0,
            'monthly' => $this->monthlySeries($vendorId, 6),
        ];
    }

    /**
     * @return list<array{month: string, revenue: float, commission: float, vendor_earnings: float}>
     */
    private function monthlySeries(?int $vendorId, int $months): array
    {
        $series = [];
        $cancelled = VendorOrderStatus::Cancelled->value;

        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $label = $start->format('M Y');

            $query = VendorOrderMapping::query()
                ->whereBetween('created_at', [$start, $end])
                ->where('status', '!=', $cancelled);

            if ($vendorId) {
                $query->where('vendor_id', $vendorId);
            }

            $revenue = (float) (clone $query)->sum('total_amount');
            $commission = (float) (clone $query)->sum('commission_amount');

            $series[] = [
                'month' => $label,
                'revenue' => round($revenue, 2),
                'commission' => round($commission, 2),
                'vendor_earnings' => round(max(0, $revenue - $commission), 2),
            ];
        }

        return $series;
    }
}
