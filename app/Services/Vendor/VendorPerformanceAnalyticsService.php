<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VendorPerformanceAnalyticsService
{
    /** @var list<string> */
    public const PERIODS = ['week', 'month', 'quarter', 'year'];

    /**
     * Mobile Performance Analytics screen payload.
     *
     * @return array<string, mixed>
     */
    public function build(Vendor $vendor, string $period = 'month'): array
    {
        $period = $this->normalizePeriod($period);
        [$start, $end, $previousStart, $previousEnd] = $this->periodBounds($period);

        $activeProducts = VendorProduct::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->count();

        $ordersQuery = VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$start, $end]);

        $completedOrders = (int) (clone $ordersQuery)
            ->where('status', VendorOrderStatus::Delivered->value)
            ->count();

        $totalOrdersInPeriod = (int) (clone $ordersQuery)->count();

        $cancelledOrders = (int) (clone $ordersQuery)
            ->where('status', VendorOrderStatus::Cancelled->value)
            ->count();

        $revenue = (float) (clone $ordersQuery)
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('total_amount');

        $previousRevenue = (float) VendorOrderMapping::query()
            ->where('vendor_id', $vendor->id)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->whereNotIn('status', [VendorOrderStatus::Cancelled->value])
            ->sum('total_amount');

        $previousActiveProducts = (int) VendorProduct::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->where('created_at', '<=', $previousEnd)
            ->count();

        $productGrowth = $this->growthPercent($activeProducts, max(1, $previousActiveProducts));
        $totalViews = $this->estimateViews($vendor->id, $start, $end, $activeProducts, $completedOrders);
        $avgOrderValue = $completedOrders > 0
            ? round($revenue / $completedOrders, 2)
            : 0.0;
        $conversionRate = $totalViews > 0
            ? round(($completedOrders / $totalViews) * 100, 1)
            : 0.0;
        $returnRate = $totalOrdersInPeriod > 0
            ? round(($cancelledOrders / $totalOrdersInPeriod) * 100, 1)
            : 0.0;

        return [
            'title' => 'Analytics',
            'subtitle' => 'Sales, orders & performance',
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'filters' => $this->filters($period),
            'overview' => [
                'title' => 'Overview',
                'period_label' => $this->overviewPeriodLabel($period),
                'total_products' => [
                    'value' => $activeProducts,
                    'subtitle' => 'Active in catalog',
                    'growth_percent' => $productGrowth,
                    'growth_display' => $this->formatGrowth($productGrowth),
                ],
                'total_orders' => [
                    'value' => $completedOrders,
                    'subtitle' => 'Completed orders',
                ],
                'total_revenue' => [
                    'value' => round($revenue, 2),
                    'currency' => 'AED',
                    'subtitle' => 'Gross earnings',
                    'display' => $this->formatAed($revenue),
                    'growth_percent' => $this->growthPercent($revenue, $previousRevenue),
                    'growth_display' => $this->formatGrowth($this->growthPercent($revenue, $previousRevenue)),
                ],
                'total_views' => [
                    'value' => $totalViews,
                    'subtitle' => 'Product impressions',
                ],
            ],
            'performance_metrics' => [
                'conversion_rate' => [
                    'value' => $conversionRate,
                    'unit' => '%',
                    'subtitle' => 'View to Order ratio',
                    'display' => number_format($conversionRate, 1).'%',
                ],
                'avg_order_value' => [
                    'value' => $avgOrderValue,
                    'currency' => 'AED',
                    'subtitle' => 'Per transaction',
                    'display' => 'AED '.number_format($avgOrderValue, 0),
                ],
                'satisfaction' => [
                    'value' => 0,
                    'max' => 5,
                    'subtitle' => 'Customer rating',
                    'display' => '0/5',
                    'available' => false,
                ],
                'return_rate' => [
                    'value' => $returnRate,
                    'unit' => '%',
                    'subtitle' => 'Product returns',
                    'display' => number_format($returnRate, 1).'%',
                ],
            ],
            'trends' => [
                'daily_performance' => [
                    'title' => 'Daily Performance',
                    'data_points' => $this->dailyPerformanceSeries($vendor->id, $end),
                    'data_points_count' => 7,
                ],
                'weekly_revenue' => [
                    'title' => 'Weekly Revenue',
                    'data_points' => $this->weeklyRevenueSeries($vendor->id, $end),
                    'data_points_count' => 7,
                ],
            ],
            'top_products' => $this->topProducts($vendor->id, $start, $end, $previousStart, $previousEnd),
            'recent_activity' => $this->recentActivity($vendor->id),
            'actions' => [
                [
                    'id' => 'export_report',
                    'label' => 'Export Report',
                    'available' => true,
                    'type' => 'download',
                    'method' => 'GET',
                    'path' => '/api/vendor/analytics/performance/export',
                    'query_params' => ['period' => $period],
                    'file_format' => 'csv',
                ],
                [
                    'id' => 'share_analytics',
                    'label' => 'Share Analytics',
                    'available' => true,
                    'type' => 'share',
                    'method' => 'POST',
                    'path' => '/api/vendor/analytics/performance/share',
                    'query_params' => ['period' => $period],
                ],
            ],
        ];
    }

    /**
     * CSV rows for analytics export (Excel-compatible).
     *
     * @return list<list<string>>
     */
    public function buildCsvRows(Vendor $vendor, string $period = 'month'): array
    {
        $period = $this->normalizePeriod($period);
        $analytics = $this->build($vendor, $period);
        $businessName = $vendor->profile?->business_name ?? 'Vendor';

        $rows = [
            ['Vendor Performance Analytics Report'],
            ['Business Name', $businessName],
            ['Report Period', $analytics['period_label']],
            ['Generated On', now()->format('Y-m-d H:i:s')],
            [],
            ['Overview'],
            ['Metric', 'Value', 'Description', 'Growth %'],
        ];

        foreach ([
            'total_products' => 'Total Products',
            'total_orders' => 'Total Orders',
            'total_revenue' => 'Total Revenue',
            'total_views' => 'Total Views',
        ] as $key => $label) {
            $item = $analytics['overview'][$key];
            $rows[] = [
                $label,
                $this->overviewMetricValue($item),
                (string) ($item['subtitle'] ?? ''),
                (string) ($item['growth_display'] ?? '—'),
            ];
        }

        $rows[] = [];
        $rows[] = ['Performance Metrics'];
        $rows[] = ['Metric', 'Value', 'Description'];
        foreach ([
            'conversion_rate' => 'Conversion Rate',
            'avg_order_value' => 'Average Order Value',
            'satisfaction' => 'Customer Satisfaction',
            'return_rate' => 'Return Rate',
        ] as $key => $label) {
            $metric = $analytics['performance_metrics'][$key];
            $rows[] = [
                $label,
                $this->metricDisplayValue($metric),
                (string) ($metric['subtitle'] ?? ''),
            ];
        }

        $rows[] = [];
        $rows[] = ['Top Products'];
        $rows[] = ['Rank', 'Product Name', 'Orders', 'Revenue', 'Growth %'];
        if ($analytics['top_products'] === []) {
            $rows[] = ['—', 'No product sales in this period', '0', 'AED 0', '—'];
        } else {
            foreach ($analytics['top_products'] as $product) {
                $rows[] = [
                    (string) $product['rank'],
                    (string) $product['name'],
                    (string) $product['orders'],
                    (string) $product['revenue_display'],
                    (string) $product['growth_display'],
                ];
            }
        }

        $rows[] = [];
        $rows[] = ['Recent Activity'];
        $rows[] = ['Activity Type', 'Title', 'Value', 'Time'];
        if ($analytics['recent_activity'] === []) {
            $rows[] = ['—', 'No recent activity in this period', '—', '—'];
        } else {
            foreach ($analytics['recent_activity'] as $activity) {
                $rows[] = [
                    ucfirst((string) $activity['type']),
                    (string) $activity['title'],
                    (string) $activity['value'],
                    (string) $activity['time_ago'],
                ];
            }
        }

        $rows[] = [];
        $rows[] = ['Daily Performance (Last 7 Days)'];
        $rows[] = ['Day', 'Orders', 'Revenue (AED)'];
        foreach ($analytics['trends']['daily_performance']['data_points'] as $point) {
            $rows[] = [
                (string) $point['label'],
                (string) $point['orders'],
                $this->formatAed((float) $point['revenue']),
            ];
        }

        $rows[] = [];
        $rows[] = ['Weekly Revenue (Last 7 Weeks)'];
        $rows[] = ['Week', 'Revenue (AED)'];
        foreach ($analytics['trends']['weekly_revenue']['data_points'] as $point) {
            $rows[] = [
                (string) $point['label'],
                $this->formatAed((float) $point['revenue']),
            ];
        }

        return $rows;
    }

    public function buildCsvString(Vendor $vendor, string $period = 'month'): string
    {
        return $this->prependUtf8Bom($this->rowsToCsv($this->buildCsvRows($vendor, $period)));
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function rowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private function prependUtf8Bom(string $csv): string
    {
        return "\xEF\xBB\xBF".$csv;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function overviewMetricValue(array $item): string
    {
        if (! empty($item['display'])) {
            return (string) $item['display'];
        }

        $value = $item['value'] ?? 0;

        if (($item['currency'] ?? null) === 'AED') {
            return $this->formatAed((float) $value);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $metric
     */
    private function metricDisplayValue(array $metric): string
    {
        if (! empty($metric['display'])) {
            return (string) $metric['display'];
        }

        $value = $metric['value'] ?? 0;
        $unit = $metric['unit'] ?? null;

        if ($unit === '%') {
            return number_format((float) $value, 1).'%';
        }

        if (($metric['currency'] ?? null) === 'AED') {
            return $this->formatAed((float) $value);
        }

        return (string) $value;
    }

    public function exportFilename(string $period = 'month'): string
    {
        $period = $this->normalizePeriod($period);

        return 'vendor_analytics_'.$period.'_'.now()->format('Y-m-d_His').'.csv';
    }

    public function normalizePeriod(string $period): string
    {
        $period = strtolower(trim($period));

        return in_array($period, self::PERIODS, true) ? $period : 'month';
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: Carbon, 3: Carbon}
     */
    private function periodBounds(string $period): array
    {
        $end = Carbon::now()->endOfDay();

        return match ($period) {
            'week' => [
                Carbon::now()->subDays(6)->startOfDay(),
                $end,
                Carbon::now()->subDays(13)->startOfDay(),
                Carbon::now()->subDays(7)->endOfDay(),
            ],
            'quarter' => [
                Carbon::now()->subMonths(3)->startOfMonth()->startOfDay(),
                $end,
                Carbon::now()->subMonths(6)->startOfMonth()->startOfDay(),
                Carbon::now()->subMonths(3)->endOfMonth()->endOfDay(),
            ],
            'year' => [
                Carbon::now()->subYear()->startOfDay(),
                $end,
                Carbon::now()->subYears(2)->startOfDay(),
                Carbon::now()->subYear()->endOfDay(),
            ],
            default => [
                Carbon::now()->startOfMonth()->startOfDay(),
                $end,
                Carbon::now()->subMonth()->startOfMonth()->startOfDay(),
                Carbon::now()->subMonth()->endOfMonth()->endOfDay(),
            ],
        };
    }

    /**
     * @return list<array{id: string, label: string, selected: bool}>
     */
    private function filters(string $selected): array
    {
        return array_map(fn (string $id) => [
            'id' => $id,
            'label' => ucfirst($id),
            'selected' => $id === $selected,
        ], self::PERIODS);
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'week' => 'This Week',
            'quarter' => 'This Quarter',
            'year' => 'This Year',
            default => 'This Month',
        };
    }

    private function overviewPeriodLabel(string $period): string
    {
        return $this->periodLabel($period);
    }

    private function growthPercent(float|int $current, float|int $previous): float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function formatGrowth(float $percent): string
    {
        $sign = $percent >= 0 ? '+' : '';

        return $sign.number_format($percent, 1).'%';
    }

    private function formatAed(float $amount): string
    {
        return 'AED '.number_format($amount, 0);
    }

    private function estimateViews(int $vendorId, Carbon $start, Carbon $end, int $activeProducts, int $completedOrders): int
    {
        $productIds = VendorProduct::where('vendor_id', $vendorId)->pluck('product_id');

        $unitsSold = 0;
        if ($productIds->isNotEmpty()) {
            $unitsSold = (int) OrderItem::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('order.vendorMappings', fn ($q) => $q->where('vendor_id', $vendorId))
                ->whereBetween('created_at', [$start, $end])
                ->sum('quantity');
        }

        return max(0, ($activeProducts * 80) + ($unitsSold * 12) + ($completedOrders * 18));
    }

    /**
     * @return list<array{label: string, orders: int, revenue: float}>
     */
    private function dailyPerformanceSeries(int $vendorId, Carbon $end): array
    {
        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $end->copy()->subDays($i);
            $start = $day->copy()->startOfDay();
            $finish = $day->copy()->endOfDay();
            $label = $day->format('D');

            $base = VendorOrderMapping::query()
                ->where('vendor_id', $vendorId)
                ->whereBetween('created_at', [$start, $finish])
                ->where('status', '!=', VendorOrderStatus::Cancelled->value);

            $series[] = [
                'label' => $label,
                'orders' => (int) (clone $base)->count(),
                'revenue' => round((float) (clone $base)->sum('total_amount'), 2),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{label: string, revenue: float}>
     */
    private function weeklyRevenueSeries(int $vendorId, Carbon $end): array
    {
        $series = [];

        for ($i = 6; $i >= 0; $i--) {
            $weekEnd = $end->copy()->subWeeks($i)->endOfWeek();
            $weekStart = $weekEnd->copy()->startOfWeek();
            $label = 'W'.$weekEnd->weekOfYear;

            $revenue = (float) VendorOrderMapping::query()
                ->where('vendor_id', $vendorId)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->where('status', '!=', VendorOrderStatus::Cancelled->value)
                ->sum('total_amount');

            $series[] = [
                'label' => $label,
                'revenue' => round($revenue, 2),
            ];
        }

        return $series;
    }

    /**
     * @return list<array{rank: int, product_id: int, name: string, orders: int, revenue: float, revenue_display: string, growth_percent: float, growth_display: string}>
     */
    private function topProducts(
        int $vendorId,
        Carbon $start,
        Carbon $end,
        Carbon $previousStart,
        Carbon $previousEnd
    ): array {
        $productIds = VendorProduct::where('vendor_id', $vendorId)->pluck('product_id');

        if ($productIds->isEmpty()) {
            return [];
        }

        $current = OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as units_sold'), DB::raw('SUM(subtotal) as revenue'))
            ->whereIn('product_id', $productIds)
            ->whereHas('order.vendorMappings', fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('product_id')
            ->orderByDesc('revenue')
            ->limit(4)
            ->get();

        $rank = 1;
        $items = [];

        foreach ($current as $row) {
            $previousRevenue = (float) OrderItem::query()
                ->where('product_id', $row->product_id)
                ->whereHas('order.vendorMappings', fn ($q) => $q->where('vendor_id', $vendorId))
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->sum('subtotal');

            $revenue = (float) $row->revenue;
            $growth = $this->growthPercent($revenue, $previousRevenue);
            $product = Product::find($row->product_id);

            $items[] = [
                'rank' => $rank++,
                'product_id' => (int) $row->product_id,
                'name' => $product?->name ?? 'Product #'.$row->product_id,
                'orders' => (int) $row->units_sold,
                'revenue' => round($revenue, 2),
                'revenue_display' => $this->formatAed($revenue),
                'growth_percent' => $growth,
                'growth_display' => $this->formatGrowth($growth),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{type: string, icon: string, title: string, time_ago: string, value: string, created_at: string}>
     */
    private function recentActivity(int $vendorId): array
    {
        $activities = [];

        $orders = VendorOrderMapping::with(['order.items.product'])
            ->where('vendor_id', $vendorId)
            ->latest()
            ->limit(4)
            ->get();

        foreach ($orders as $mapping) {
            $productName = $mapping->order?->items?->first()?->product?->name ?? 'your store';
            $activities[] = [
                'type' => 'order',
                'icon' => 'shopping_bag',
                'title' => 'New order for '.$productName,
                'time_ago' => $mapping->created_at?->diffForHumans() ?? '',
                'value' => '+AED '.number_format((float) $mapping->total_amount, 0),
                'created_at' => $mapping->created_at?->toIso8601String(),
            ];
        }

        return $activities;
    }
}
