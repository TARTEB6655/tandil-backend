<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.flash />

        <x-admin.vendor.page-header
            title="Vendor Overview"
            description="Real-time snapshot of your multi-vendor marketplace — vendors, products, orders, and revenue.">
            <x-slot:actions>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.index')">All Vendors</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.export')">Export</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="primary" :href="route('admin.vendors.pending')">Pending Approvals</x-admin.vendor.btn>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <x-admin.vendor.nav />

        {{-- KPI grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6">
            @foreach($data['kpis'] as $kpi)
                <x-admin.vendor.kpi-trend-card
                    :label="$kpi['label']"
                    :value="$kpi['value']"
                    :hint="$kpi['hint']"
                    :icon="$kpi['icon']"
                    :trend="$kpi['trend']"
                    :accent="$kpi['accent']" />
            @endforeach
        </div>

        {{-- Charts row 1 --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-dashboard.chart-card title="Revenue Growth" canvasId="overviewRevenueChart" />
            <x-dashboard.chart-card title="Orders Analytics" canvasId="overviewOrdersChart" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-dashboard.chart-card title="Vendor Registrations" canvasId="overviewVendorRegChart" />
            <x-dashboard.chart-card title="Product Growth" canvasId="overviewProductGrowthChart" />
            <x-dashboard.chart-card title="Order Status" canvasId="overviewOrderStatusChart" />
        </div>

        <x-dashboard.chart-card title="Revenue vs Commission" canvasId="overviewRevenueCommissionChart" />

        {{-- Top performers --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-admin.vendor.card title="Top 10 Vendors by Revenue">
                <div class="space-y-2">
                    @forelse($data['top_vendors'] as $i => $row)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2.5 transition-colors hover:bg-gray-100 dark:bg-gray-800/50 dark:hover:bg-gray-800">
                            <div class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/40">{{ $i + 1 }}</span>
                                <a href="{{ route('admin.vendors.show', $row['vendor_id']) }}" class="text-sm font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100">{{ $row['business_name'] ?? 'Vendor #'.$row['vendor_id'] }}</a>
                            </div>
                            <span class="text-xs text-gray-500">AED {{ number_format($row['revenue'], 2) }} · {{ $row['order_count'] }} orders</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No vendor revenue data yet.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Top Selling Products">
                <div class="space-y-2">
                    @forelse($data['charts']['top_products'] as $product)
                        <div class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2.5 dark:bg-gray-800/50">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $product['vendor'] }}</p>
                            </div>
                            <span class="shrink-0 text-xs text-gray-500">{{ $product['units_sold'] }} sold · AED {{ number_format($product['revenue'], 2) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No product sales yet.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>
        </div>

        {{-- Recent activity --}}
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            <x-admin.vendor.card title="Recently Registered" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['vendors'] as $v)
                        <a href="{{ route('admin.vendors.show', $v) }}" class="flex items-center justify-between px-5 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <div>
                                <p class="text-sm font-medium">{{ $v->profile?->business_name }}</p>
                                <p class="text-xs text-gray-500">{{ $v->created_at?->diffForHumans() }}</p>
                            </div>
                            <x-admin.vendor.status-badge :status="$v->status" />
                        </a>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">No vendors yet.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Pending Approvals" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['pending_approvals'] as $v)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div>
                                <p class="text-sm font-medium">{{ $v->profile?->business_name }}</p>
                                <p class="text-xs text-gray-500">{{ $v->profile?->email }}</p>
                            </div>
                            <x-admin.vendor.btn variant="brand" :href="route('admin.vendors.show', $v)">Review</x-admin.vendor.btn>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">No pending approvals.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Latest Products" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['products'] as $vp)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $vp->product?->name }}</p>
                                <p class="text-xs text-gray-500">{{ $vp->vendor?->profile?->business_name }}</p>
                            </div>
                            <span @class([
                                'shrink-0 rounded-md px-2 py-0.5 text-[10px] font-medium ring-1',
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $vp->isMarketplaceVisible(),
                                'bg-gray-100 text-gray-600 ring-gray-500/20' => ! $vp->isMarketplaceVisible(),
                            ])>{{ $vp->isMarketplaceVisible() ? 'Live' : 'Hidden' }}</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">No products yet.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Recent Orders" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['orders'] as $m)
                        <a href="{{ route('admin.vendors.orders.show', [$m->vendor, $m]) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <div>
                                <p class="text-sm font-medium">#{{ $m->order_id }}</p>
                                <p class="text-xs text-gray-500">{{ $m->vendor?->profile?->business_name }}</p>
                            </div>
                            <span class="text-sm font-medium text-emerald-600">AED {{ number_format($m->total_amount, 2) }}</span>
                        </a>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">No orders yet.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Low Stock" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['low_stock'] as $vp)
                        <div class="flex items-center justify-between px-5 py-3">
                            <p class="truncate text-sm font-medium">{{ $vp->product?->name }}</p>
                            <span class="text-xs font-medium text-amber-600">{{ $vp->stockQuantity() }} left</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">All stock levels healthy.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Disabled by Admin" :padding="false">
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($data['recent']['disabled_products'] as $vp)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">{{ $vp->product?->name }}</p>
                                <p class="text-xs text-gray-500">{{ $vp->vendor?->profile?->business_name }}</p>
                            </div>
                            <span class="text-xs font-medium text-rose-600">Disabled</span>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-gray-500">No admin-disabled products.</p>
                    @endforelse
                </div>
            </x-admin.vendor.card>
        </div>

        {{-- Quick actions --}}
        <x-admin.vendor.card title="Quick actions">
            <div class="flex flex-wrap gap-2">
                <x-admin.vendor.btn variant="primary" :href="route('admin.vendors.pending')">Pending Approvals</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.index')">View All Vendors</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.marketplace.products.index')">View Products</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.marketplace.orders.index')">View Orders</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.revenue')">Revenue & Commissions</x-admin.vendor.btn>
                <x-admin.vendor.btn variant="secondary" :href="route('admin.vendors.reports')">Export Reports</x-admin.vendor.btn>
            </div>
        </x-admin.vendor.card>
    </x-admin.vendor.shell>

    @push('scripts')
    <script>
        const charts = @json($data['charts']);
        const revenueGrowth = charts.revenue_growth || [];
        const ordersGrowth = charts.orders || [];
        const vendorReg = charts.vendor_registrations || [];
        const productGrowth = charts.product_growth || [];
        const orderStatus = charts.order_status || [];
        const revComm = charts.revenue_vs_commission || [];

        const chartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { labels: { boxWidth: 10, font: { size: 11 } } } },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { font: { size: 11 } } },
            },
        };

        const revCtx = document.getElementById('overviewRevenueChart');
        if (revCtx) new Chart(revCtx, {
            type: 'line',
            data: {
                labels: revenueGrowth.map(i => i.month),
                datasets: [{ label: 'Revenue (AED)', data: revenueGrowth.map(i => i.value), borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.08)', fill: true, tension: 0.4, borderWidth: 2, pointRadius: 3 }],
            },
            options: { ...chartDefaults, plugins: { legend: { display: false } } },
        });

        const ordCtx = document.getElementById('overviewOrdersChart');
        if (ordCtx) new Chart(ordCtx, {
            type: 'bar',
            data: {
                labels: ordersGrowth.map(i => i.month),
                datasets: [{ label: 'Orders', data: ordersGrowth.map(i => i.value), backgroundColor: 'rgba(16,185,129,0.75)', borderRadius: 6 }],
            },
            options: { ...chartDefaults, plugins: { legend: { display: false } } },
        });

        const vRegCtx = document.getElementById('overviewVendorRegChart');
        if (vRegCtx) new Chart(vRegCtx, {
            type: 'line',
            data: {
                labels: vendorReg.map(i => i.month),
                datasets: [{ label: 'New vendors', data: vendorReg.map(i => i.value), borderColor: '#0ea5e9', tension: 0.35, fill: false }],
            },
            options: { ...chartDefaults, plugins: { legend: { display: false } } },
        });

        const pGrowthCtx = document.getElementById('overviewProductGrowthChart');
        if (pGrowthCtx) new Chart(pGrowthCtx, {
            type: 'bar',
            data: {
                labels: productGrowth.map(i => i.month),
                datasets: [{ label: 'New products', data: productGrowth.map(i => i.value), backgroundColor: 'rgba(139,92,246,0.75)', borderRadius: 6 }],
            },
            options: { ...chartDefaults, plugins: { legend: { display: false } } },
        });

        const statusCtx = document.getElementById('overviewOrderStatusChart');
        if (statusCtx && orderStatus.length) new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: orderStatus.map(i => i.status),
                datasets: [{ data: orderStatus.map(i => i.count), backgroundColor: ['#f59e0b','#3b82f6','#10b981','#8b5cf6','#ef4444','#6b7280'], borderWidth: 0 }],
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } },
        });

        const rcCtx = document.getElementById('overviewRevenueCommissionChart');
        if (rcCtx) new Chart(rcCtx, {
            type: 'bar',
            data: {
                labels: revComm.map(i => i.month),
                datasets: [
                    { label: 'Revenue', data: revComm.map(i => i.revenue), backgroundColor: 'rgba(79,70,229,0.75)', borderRadius: 4 },
                    { label: 'Commission', data: revComm.map(i => i.commission), backgroundColor: 'rgba(245,158,11,0.75)', borderRadius: 4 },
                ],
            },
            options: { ...chartDefaults, plugins: { legend: { position: 'bottom' } } },
        });
    </script>
    @endpush
</x-admin-layout>
