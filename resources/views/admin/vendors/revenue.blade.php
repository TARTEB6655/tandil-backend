<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav />

        <x-admin.vendor.page-header
            title="Revenue Management"
            description="Platform-wide marketplace revenue, commissions, and payouts." />

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
            <x-admin.vendor.kpi-card label="Total Revenue" :value="'AED '.number_format($revenue['total_revenue'], 2)" accent="text-amber-600" />
            <x-admin.vendor.kpi-card label="Vendor Earnings" :value="'AED '.number_format($revenue['vendor_earnings'], 2)" accent="text-emerald-600" />
            <x-admin.vendor.kpi-card label="Platform Earnings" :value="'AED '.number_format($revenue['platform_earnings'], 2)" accent="text-indigo-600" />
            <x-admin.vendor.kpi-card label="Commission" :value="'AED '.number_format($revenue['commission'], 2)" accent="text-purple-600" />
            <x-admin.vendor.kpi-card label="Pending Payments" :value="'AED '.number_format($revenue['pending_payments'], 2)" accent="text-yellow-600" />
            <x-admin.vendor.kpi-card label="Withdrawals" :value="'AED '.number_format($revenue['withdrawals'], 2)" accent="text-gray-600" />
        </div>

        <x-dashboard.chart-card title="Monthly Revenue" canvasId="platformRevenueChart" />
    </x-admin.vendor.shell>

    @push('scripts')
    <script>
        const monthly = @json($revenue['monthly']);
        new Chart(document.getElementById('platformRevenueChart'), {
            type: 'line',
            data: {
                labels: monthly.map(i => i.month),
                datasets: [
                    { label: 'Gross Revenue', data: monthly.map(i => i.revenue), borderColor: '#6366f1', tension: 0.35, fill: false },
                    { label: 'Vendor Earnings', data: monthly.map(i => i.vendor_earnings), borderColor: '#10b981', tension: 0.35, fill: false },
                    { label: 'Commission', data: monthly.map(i => i.commission), borderColor: '#f59e0b', tension: 0.35, fill: false },
                ],
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
        });
    </script>
    @endpush
</x-admin-layout>
