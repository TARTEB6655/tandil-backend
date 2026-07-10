<x-admin-layout>
    <div class="space-y-6">
        <x-admin.vendor.nav :vendor="$vendor" />
        <div>
            <h1 class="text-2xl font-semibold">Revenue — {{ $vendor->profile?->business_name }}</h1>
            <p class="text-sm text-gray-500">Earnings, commission, and payout overview.</p>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <x-admin.vendor.kpi-card label="Total Revenue" :value="'AED '.number_format($revenue['total_revenue'], 2)" accent="text-amber-600" />
            <x-admin.vendor.kpi-card label="Vendor Earnings" :value="'AED '.number_format($revenue['vendor_earnings'], 2)" accent="text-emerald-600" />
            <x-admin.vendor.kpi-card label="Platform Earnings" :value="'AED '.number_format($revenue['platform_earnings'], 2)" accent="text-indigo-600" />
            <x-admin.vendor.kpi-card label="Commission" :value="'AED '.number_format($revenue['commission'], 2)" accent="text-purple-600" />
            <x-admin.vendor.kpi-card label="Pending Payments" :value="'AED '.number_format($revenue['pending_payments'], 2)" accent="text-yellow-600" />
            <x-admin.vendor.kpi-card label="Wallet Balance" :value="'AED '.number_format($revenue['wallet_balance'], 2)" accent="text-sky-600" />
        </div>

        <x-dashboard.chart-card title="Monthly Revenue Breakdown" canvasId="vendorRevenueBreakdownChart" />
    </div>

    @push('scripts')
    <script>
        const monthly = @json($revenue['monthly']);
        const ctx = document.getElementById('vendorRevenueBreakdownChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthly.map(i => i.month),
                    datasets: [
                        { label: 'Revenue', data: monthly.map(i => i.revenue), backgroundColor: 'rgba(99,102,241,0.75)', borderRadius: 6 },
                        { label: 'Vendor Earnings', data: monthly.map(i => i.vendor_earnings), backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6 },
                        { label: 'Commission', data: monthly.map(i => i.commission), backgroundColor: 'rgba(245,158,11,0.75)', borderRadius: 6 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
            });
        }
    </script>
    @endpush
</x-admin-layout>
