<x-admin-layout>
    <div class="space-y-6">
        <x-admin.vendor.nav />
        <div>
            <h1 class="text-2xl font-semibold">Vendor Analytics</h1>
            <p class="text-sm text-gray-500">Marketplace performance insights and growth trends.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-admin.vendor.kpi-card label="Total Vendors" :value="number_format($data['overview']['vendors']['total'] ?? 0)" />
            <x-admin.vendor.kpi-card label="Approved" :value="number_format($data['overview']['vendors']['approved'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.kpi-card label="Gross Revenue" :value="'AED '.number_format($data['overview']['revenue']['gross'] ?? 0, 2)" accent="text-amber-600" />
            <x-admin.vendor.kpi-card label="Platform Commission" :value="'AED '.number_format($data['overview']['revenue']['platform_commission'] ?? 0, 2)" accent="text-indigo-600" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-dashboard.chart-card title="Revenue Growth" canvasId="insightsRevenueChart" />
            <x-dashboard.chart-card title="Order Growth" canvasId="insightsOrdersChart" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border bg-white/80 p-5 backdrop-blur dark:bg-gray-900/70">
                <h2 class="mb-4 font-semibold">Top Selling Vendors</h2>
                <div class="space-y-3">
                    @foreach($data['top_selling_vendors'] as $row)
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-900/50">
                            <span class="font-medium">{{ $row['business_name'] ?? 'Vendor #'.$row['vendor_id'] }}</span>
                            <span class="text-sm text-gray-600">AED {{ number_format($row['revenue'], 2) }} · {{ $row['order_count'] }} orders</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="rounded-2xl border bg-white/80 p-5 backdrop-blur dark:bg-gray-900/70">
                <h2 class="mb-4 font-semibold">New Vendors</h2>
                <div class="space-y-3">
                    @foreach($data['new_vendors'] as $row)
                        <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 dark:bg-gray-900/50">
                            <a href="{{ route('admin.vendors.show', $row['vendor_id']) }}" class="font-medium text-indigo-600">{{ $row['business_name'] }}</a>
                            <span class="text-xs text-gray-500">{{ $row['created_at'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const revenueGrowth = @json($data['revenue_growth']);
        const orderGrowth = @json($data['order_growth']);
        new Chart(document.getElementById('insightsRevenueChart'), {
            type: 'line',
            data: { labels: revenueGrowth.map(i => i.month), datasets: [{ label: 'Revenue', data: revenueGrowth.map(i => i.value), borderColor: '#6366f1', fill: true, backgroundColor: 'rgba(99,102,241,0.1)', tension: 0.35 }] },
            options: { responsive: true, maintainAspectRatio: false },
        });
        new Chart(document.getElementById('insightsOrdersChart'), {
            type: 'bar',
            data: { labels: orderGrowth.map(i => i.month), datasets: [{ label: 'Orders', data: orderGrowth.map(i => i.value), backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6 }] },
            options: { responsive: true, maintainAspectRatio: false },
        });
    </script>
    @endpush
</x-admin-layout>
