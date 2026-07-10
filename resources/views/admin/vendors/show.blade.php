<x-admin-layout>
    <div class="mx-auto max-w-7xl space-y-6">
        @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

        <x-admin.vendor.nav :vendor="$vendor" />

        <div class="flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200/80 bg-white/80 p-5 backdrop-blur dark:border-gray-700 dark:bg-gray-900/70">
            <div class="flex items-start gap-4">
                @if($vendor->logo_url)
                    <img src="{{ $vendor->logo_url }}" class="h-16 w-16 rounded-2xl border object-cover" alt="" />
                @else
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-100 text-xl font-bold text-indigo-600">{{ strtoupper(substr($vendor->profile?->business_name ?? 'V', 0, 1)) }}</div>
                @endif
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name }}</h1>
                    <p class="text-sm text-gray-500">{{ $vendor->profile?->owner_name }} · {{ $vendor->profile?->email }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-admin.vendor.status-badge :status="$vendor->status" />
                        @if($isVerified ?? false)
                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Verified</span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(in_array($vendor->status, ['pending', 'under_review']))
                    <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">@csrf<button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-medium text-white">Approve</button></form>
                @endif
                @if($vendor->status === 'approved')
                    <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf<button class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-medium text-white">Suspend</button></form>
                @endif
                <a href="{{ route('admin.vendors.edit', $vendor) }}" class="rounded-xl border px-4 py-2 text-sm font-medium">Edit</a>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            <x-admin.vendor.kpi-card label="Total Revenue" :value="'AED '.number_format($revenue['total_revenue'] ?? 0, 2)" accent="text-amber-600" />
            <x-admin.vendor.kpi-card label="Total Orders" :value="number_format($statistics['total_orders'] ?? 0)" accent="text-purple-600" />
            <x-admin.vendor.kpi-card label="Pending Orders" :value="number_format($statistics['pending_orders'] ?? 0)" accent="text-yellow-600" />
            <x-admin.vendor.kpi-card label="Delivered" :value="number_format($statistics['completed_orders'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.kpi-card label="Products" :value="number_format($metrics['total_products'] ?? 0)" :subtitle="($metrics['active_products'] ?? 0).' active'" accent="text-blue-600" />
            <x-admin.vendor.kpi-card label="Wallet Balance" :value="'AED '.number_format($revenue['wallet_balance'] ?? 0, 2)" accent="text-indigo-600" />
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-admin.vendor.kpi-card label="Out of Stock" :value="number_format($statistics['out_of_stock_products'] ?? 0)" accent="text-rose-600" />
            <x-admin.vendor.kpi-card label="Low Stock" :value="number_format($statistics['low_stock_products'] ?? 0)" accent="text-orange-600" />
            <x-admin.vendor.kpi-card label="Commission" :value="'AED '.number_format($revenue['commission'] ?? 0, 2)" accent="text-gray-700" />
            <x-admin.vendor.kpi-card label="Net Earnings" :value="'AED '.number_format($statistics['net_earnings'] ?? 0, 2)" accent="text-emerald-600" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-dashboard.chart-card title="Revenue (6 months)" canvasId="vendorAdminRevenueChart" />
            <x-dashboard.chart-card title="Orders by Status" canvasId="vendorAdminOrdersChart" />
        </div>

        @include('admin.vendors.partials.controls', ['vendor' => $vendor, 'isVerified' => $isVerified ?? false])

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @include('admin.vendors.partials.recent-products', ['vendor' => $vendor, 'recentProducts' => $recentProducts])
            @include('admin.vendors.partials.recent-orders', ['recentOrders' => $recentOrders])
        </div>
    </div>

    @push('scripts')
    <script>
        const monthly = @json($revenue['monthly'] ?? []);
        const revenueCtx = document.getElementById('vendorAdminRevenueChart');
        if (revenueCtx && monthly.length) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: monthly.map(i => i.month),
                    datasets: [{
                        label: 'Revenue (AED)',
                        data: monthly.map(i => i.revenue),
                        borderColor: 'rgb(99, 102, 241)',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }

        const statusData = @json($analytics['orders_by_status'] ?? []);
        const ordersCtx = document.getElementById('vendorAdminOrdersChart');
        if (ordersCtx && statusData.length) {
            new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(i => i.status),
                    datasets: [{ data: statusData.map(i => i.count), backgroundColor: ['#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444','#6b7280'] }],
                },
                options: { responsive: true, maintainAspectRatio: false },
            });
        }
    </script>
    @endpush
</x-admin-layout>
