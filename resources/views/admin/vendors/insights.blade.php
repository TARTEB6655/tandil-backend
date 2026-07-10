<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav />

        <x-admin.vendor.page-header
            title="Vendor Analytics"
            description="Marketplace performance insights and growth trends." />

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
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
            <x-admin.vendor.card title="Top selling vendors">
                <div class="space-y-2">
                    @foreach($data['top_selling_vendors'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2.5 dark:bg-gray-800/50">
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['business_name'] ?? 'Vendor #'.$row['vendor_id'] }}</span>
                            <span class="text-xs text-gray-500">AED {{ number_format($row['revenue'], 2) }} · {{ $row['order_count'] }} orders</span>
                        </div>
                    @endforeach
                </div>
            </x-admin.vendor.card>
            <x-admin.vendor.card title="New vendors">
                <div class="space-y-2">
                    @foreach($data['new_vendors'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2.5 dark:bg-gray-800/50">
                            <a href="{{ route('admin.vendors.show', $row['vendor_id']) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">{{ $row['business_name'] }}</a>
                            <span class="text-xs text-gray-500">{{ $row['created_at'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-admin.vendor.card>
        </div>
    </x-admin.vendor.shell>

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
