<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.flash />

        <x-admin.vendor.nav :vendor="$vendor" />

        {{-- Profile header --}}
        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4">
                <x-admin.vendor.avatar
                    :name="$vendor->profile?->business_name ?? 'V'"
                    :src="$vendor->logo_url"
                    size="xl" />
                <div>
                    <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ $vendor->profile?->business_name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">{{ $vendor->profile?->owner_name }} · {{ $vendor->profile?->email }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-admin.vendor.status-badge :status="$vendor->status" />
                        <x-admin.vendor.verification-badge :verified="$isVerified ?? false" />
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(in_array($vendor->status, ['pending', 'under_review']))
                    <form method="POST" action="{{ route('admin.vendors.approve', $vendor) }}">@csrf
                        <x-admin.vendor.btn variant="brand" type="submit">Approve vendor</x-admin.vendor.btn>
                    </form>
                @endif
                @if($vendor->status === 'approved')
                    <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf
                        <x-admin.vendor.btn variant="secondary" type="submit">Suspend</x-admin.vendor.btn>
                    </form>
                @endif
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.edit', $vendor)">Edit vendor</x-admin.vendor.btn>
            </div>
        </div>

        {{-- Overview KPIs --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-6">
            <x-admin.vendor.stat-card label="Total Revenue" :value="'AED '.number_format($revenue['total_revenue'] ?? 0, 2)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Total Orders" :value="number_format($statistics['total_orders'] ?? 0)" />
            <x-admin.vendor.stat-card label="Active Products" :value="number_format($metrics['active_products'] ?? 0)" accent="text-indigo-600" />
            <x-admin.vendor.stat-card label="Disabled Products" :value="number_format($statistics['disabled_products'] ?? 0)" accent="text-rose-600" />
            <x-admin.vendor.stat-card label="Pending Orders" :value="number_format($statistics['pending_orders'] ?? 0)" accent="text-sky-600" />
            <x-admin.vendor.stat-card label="Wallet Balance" :value="'AED '.number_format($revenue['wallet_balance'] ?? 0, 2)" accent="text-violet-600" />
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <x-admin.vendor.stat-card label="Delivered Orders" :value="number_format($statistics['completed_orders'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Out of Stock" :value="number_format($statistics['out_of_stock_products'] ?? 0)" accent="text-rose-600" />
            <x-admin.vendor.stat-card label="Commission Earned" :value="'AED '.number_format($revenue['commission'] ?? 0, 2)" />
            <x-admin.vendor.stat-card label="Net Earnings" :value="'AED '.number_format($statistics['net_earnings'] ?? 0, 2)" accent="text-emerald-600" />
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            {{-- Charts --}}
            <div class="space-y-6 xl:col-span-2">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <x-dashboard.chart-card title="Revenue trend" canvasId="vendorAdminRevenueChart" />
                    <x-dashboard.chart-card title="Orders by status" canvasId="vendorAdminOrdersChart" />
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @include('admin.vendors.partials.recent-products', ['vendor' => $vendor, 'recentProducts' => $recentProducts])
                    @include('admin.vendors.partials.recent-orders', ['recentOrders' => $recentOrders])
                </div>
            </div>

            {{-- Sidebar: info + quick actions --}}
            <div class="space-y-6">
                <x-admin.vendor.card title="Vendor information">
                    <dl class="space-y-4 text-sm">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Store name</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ $vendor->profile?->business_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Contact</dt>
                            <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $vendor->profile?->email }}</dd>
                            <dd class="text-gray-500">{{ $vendor->profile?->phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Address</dt>
                            <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $vendor->profile?->address ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Registered</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $vendor->created_at?->format('M j, Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Last login</dt>
                                <dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $vendor->user?->updated_at?->diffForHumans() ?? '—' }}</dd>
                            </div>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Account status</dt>
                            <dd class="mt-2"><x-admin.vendor.status-badge :status="$vendor->status" /></dd>
                        </div>
                    </dl>
                </x-admin.vendor.card>

                <x-admin.vendor.card title="Quick actions">
                    <div class="space-y-2">
                        <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.edit', $vendor)" class="w-full">Edit vendor</x-admin.vendor.btn>
                        @if(!($isVerified ?? false))
                            <form method="POST" action="{{ route('admin.vendors.verify', $vendor) }}">@csrf
                                <x-admin.vendor.btn variant="brand" type="submit" class="w-full">Verify vendor</x-admin.vendor.btn>
                            </form>
                        @endif
                        @if($vendor->status === 'approved')
                            <form method="POST" action="{{ route('admin.vendors.suspend', $vendor) }}">@csrf
                                <x-admin.vendor.btn variant="secondary" type="submit" class="w-full">Suspend vendor</x-admin.vendor.btn>
                            </form>
                        @elseif(in_array($vendor->status, ['suspended', 'rejected', 'disabled']))
                            <form method="POST" action="{{ route('admin.vendors.activate', $vendor) }}">@csrf
                                <x-admin.vendor.btn variant="brand" type="submit" class="w-full">Activate vendor</x-admin.vendor.btn>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.vendors.disable', $vendor) }}">@csrf
                            <x-admin.vendor.btn variant="secondary" type="submit" class="w-full">Disable store</x-admin.vendor.btn>
                        </form>
                    </div>
                </x-admin.vendor.card>

                @include('admin.vendors.partials.controls', ['vendor' => $vendor, 'isVerified' => $isVerified ?? false])
            </div>
        </div>
    </x-admin.vendor.shell>

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
                        borderColor: 'rgb(79, 70, 229)',
                        backgroundColor: 'rgba(79, 70, 229, 0.08)',
                        fill: true,
                        tension: 0.4,
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                        y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
                    },
                },
            });
        }

        const statusData = @json($analytics['orders_by_status'] ?? []);
        const ordersCtx = document.getElementById('vendorAdminOrdersChart');
        if (ordersCtx && statusData.length) {
            new Chart(ordersCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(i => i.status),
                    datasets: [{
                        data: statusData.map(i => i.count),
                        backgroundColor: ['#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444','#6b7280'],
                        borderWidth: 0,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 } } } },
                },
            });
        }
    </script>
    @endpush
</x-admin-layout>
