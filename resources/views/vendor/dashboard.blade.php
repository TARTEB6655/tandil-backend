<x-vendor-layout>
    <x-dashboard.page-header :title="$dashboardTitle" :subtitle="$dashboardSubtitle" />

    @php
        $currency = $summary['currency'] ?? 'AED';
    @endphp

    {{-- Hero cards (mobile summary API alignment) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <a href="{{ route('vendor.orders.index') }}" class="group relative overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 shadow-sm transition hover:border-amber-300 hover:shadow-md sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-700/80">Total Revenue</p>
                    <p class="mt-2 text-2xl font-semibold text-amber-700 sm:text-3xl">{{ $currency }} {{ number_format($summary['revenue'] ?? 0, 2) }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ $summary['delivered_orders'] ?? 0 }} delivered orders</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
        </a>

        <a href="{{ route('vendor.orders.index', ['status' => 'pending']) }}" class="group relative overflow-hidden rounded-2xl border border-purple-200 bg-gradient-to-br from-purple-50 to-white p-5 shadow-sm transition hover:border-purple-300 hover:shadow-md sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-700/80">Pending Orders</p>
                    <p class="mt-2 text-2xl font-semibold text-purple-700 sm:text-3xl">{{ number_format($summary['pending_orders'] ?? 0) }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ number_format($summary['total_orders'] ?? 0) }} total orders</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 text-purple-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
            </div>
        </a>
    </div>

    {{-- Overview grid --}}
    <div class="mb-6">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Overview</h2>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <x-dashboard.stat-card label="Products" :value="number_format($summary['products'] ?? 0)" :subtitle="($summary['active'] ?? 0).' active'" color="blue" :href="route('vendor.products.index')">
                <x-slot:icon><svg class="h-5 w-5 sm:h-6 sm:w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg></x-slot:icon>
            </x-dashboard.stat-card>
            <x-dashboard.stat-card label="Active" :value="number_format($summary['active'] ?? 0)" subtitle="Live in catalog" color="green" :href="route('vendor.products.index')">
                <x-slot:icon><svg class="h-5 w-5 sm:h-6 sm:w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot:icon>
            </x-dashboard.stat-card>
            <x-dashboard.stat-card label="Low Stock" :value="number_format($summary['low_stock'] ?? 0)" subtitle="Needs attention" color="red" :href="route('vendor.inventory.index', ['filter' => 'low'])">
                <x-slot:icon><svg class="h-5 w-5 sm:h-6 sm:w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg></x-slot:icon>
            </x-dashboard.stat-card>
            <x-dashboard.stat-card label="Total Orders" :value="number_format($summary['total_orders'] ?? 0)" :subtitle="($summary['delivered_orders'] ?? 0).' delivered'" color="purple" :href="route('vendor.orders.index')">
                <x-slot:icon><svg class="h-5 w-5 sm:h-6 sm:w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></x-slot:icon>
            </x-dashboard.stat-card>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Quick Actions</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <a href="{{ route('vendor.products.create') }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900">Add Product</p>
                    <p class="text-xs text-gray-500">List a new item</p>
                </div>
            </a>
            <a href="{{ route('vendor.orders.index') }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900">View Orders</p>
                    <p class="text-xs text-gray-500">Manage fulfillment</p>
                </div>
            </a>
            <a href="{{ route('vendor.inventory.index') }}" class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50 text-teal-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/></svg>
                </span>
                <div>
                    <p class="font-medium text-gray-900">Inventory</p>
                    <p class="text-xs text-gray-500">Stock & thresholds</p>
                </div>
            </a>
        </div>
    </div>

    {{-- Extended metrics --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Net Earnings</p>
            <p class="mt-1 text-xl font-semibold text-green-600">{{ $currency }} {{ number_format($stats['net_earnings'] ?? 0, 2) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">In Fulfillment</p>
            <p class="mt-1 text-xl font-semibold text-blue-600">{{ number_format($stats['processing_orders'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Customers</p>
            <p class="mt-1 text-xl font-semibold text-indigo-600">{{ number_format($stats['unique_customers'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Avg. Order</p>
            <p class="mt-1 text-xl font-semibold text-gray-900">{{ $currency }} {{ number_format($stats['average_order_value'] ?? 0, 2) }}</p>
        </div>
    </div>

    {{-- Analytics --}}
    <div class="mb-4">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Analytics</h2>
    </div>
    <div class="mb-4 grid grid-cols-1 gap-4 sm:mb-6 md:mb-8 lg:grid-cols-2 lg:gap-6">
        <x-dashboard.chart-card title="Sales Overview (Revenue & Orders)" canvasId="vendorSalesOverviewChart" />
        <x-dashboard.chart-card title="Revenue Analytics (Last 6 Months)" canvasId="vendorRevenueChart" />
    </div>
    <div class="mb-4 grid grid-cols-1 gap-4 sm:mb-6 md:mb-8 lg:grid-cols-2 lg:gap-6">
        <x-dashboard.chart-card title="Order Trends by Status" canvasId="vendorOrdersStatusChart" />
        <x-dashboard.chart-card title="Monthly Earnings (Net)" canvasId="vendorEarningsChart" />
    </div>
    <div class="mb-4 grid grid-cols-1 gap-4 sm:mb-6 md:mb-8 lg:grid-cols-2 lg:gap-6">
        <x-dashboard.chart-card title="Customer Growth" canvasId="vendorCustomerGrowthChart" />
        <x-dashboard.chart-card title="Product Performance (Top 5)" canvasId="vendorProductPerformanceChart" />
    </div>

    {{-- Tables --}}
    <div class="mb-4 grid grid-cols-1 gap-4 sm:mb-6 lg:grid-cols-2 lg:gap-6">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-6 sm:py-4">
                <h3 class="text-base font-medium text-gray-900 sm:text-lg">Recent Orders</h3>
                <a href="{{ route('vendor.orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($stats['recent_orders'] as $order)
                            <tr class="transition-colors hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">
                                    <a href="{{ route('vendor.orders.show', $order['id']) }}" class="text-indigo-600 hover:text-indigo-800">#{{ $order['order_id'] }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $order['customer_name'] ?? 'Guest' }}</td>
                                <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ str_replace('_', ' ', $order['status']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-gray-900">{{ $currency }} {{ number_format($order['total_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-6 sm:py-4">
                <h3 class="text-base font-medium text-gray-900 sm:text-lg">Inventory Alerts</h3>
                <a href="{{ route('vendor.inventory.index', ['filter' => 'low']) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Manage</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse($stats['inventory_alerts'] as $alert)
                    <li class="flex items-center justify-between px-4 py-3 text-sm sm:px-6">
                        <span class="font-medium text-gray-900">{{ $alert['product_name'] }}</span>
                        <span class="text-gray-600">{{ $alert['quantity'] }} left <span class="text-gray-400">(min {{ $alert['low_stock_threshold'] }})</span></span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6">No low-stock alerts.</li>
                @endforelse
            </ul>
        </div>
    </div>

    @push('scripts')
    <script>
        const vendorChartDefaults = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } },
        };

        const salesOverview = @json($analytics['sales_overview'] ?? ['labels' => [], 'revenue' => [], 'orders' => []]);
        const salesCtx = document.getElementById('vendorSalesOverviewChart');
        if (salesCtx && salesOverview.labels?.length) {
            new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: salesOverview.labels,
                    datasets: [
                        {
                            label: 'Revenue (AED)',
                            data: salesOverview.revenue,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderRadius: 4,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Orders',
                            data: salesOverview.orders,
                            type: 'line',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.35,
                            yAxisID: 'y1',
                        },
                    ],
                },
                options: {
                    ...vendorChartDefaults,
                    scales: {
                        y: { beginAtZero: true, position: 'left' },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
                    },
                },
            });
        }

        const monthlyRevenue = @json($analytics['monthly_revenue'] ?? []);
        const revenueCtx = document.getElementById('vendorRevenueChart');
        if (revenueCtx && monthlyRevenue.length) {
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: monthlyRevenue.map(i => i.month),
                    datasets: [{
                        label: 'Revenue (AED)',
                        data: monthlyRevenue.map(i => i.amount),
                        borderColor: 'rgba(99, 102, 241, 1)',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        fill: true,
                        tension: 0.4,
                    }],
                },
                options: {
                    ...vendorChartDefaults,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => 'AED ' + Number(v).toLocaleString() },
                        },
                    },
                },
            });
        }

        const ordersByStatus = @json($analytics['orders_by_status'] ?? []);
        const statusCtx = document.getElementById('vendorOrdersStatusChart');
        if (statusCtx && ordersByStatus.length) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ordersByStatus.map(i => i.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())),
                    datasets: [{
                        data: ordersByStatus.map(i => i.count),
                        backgroundColor: [
                            'rgba(234, 179, 8, 0.85)',
                            'rgba(59, 130, 246, 0.85)',
                            'rgba(16, 185, 129, 0.85)',
                            'rgba(139, 92, 246, 0.85)',
                            'rgba(107, 114, 128, 0.85)',
                            'rgba(239, 68, 68, 0.85)',
                        ],
                        borderWidth: 2,
                        borderColor: '#fff',
                    }],
                },
                options: vendorChartDefaults,
            });
        }

        const monthlyEarnings = @json($analytics['monthly_earnings'] ?? []);
        const earningsCtx = document.getElementById('vendorEarningsChart');
        if (earningsCtx && monthlyEarnings.length) {
            new Chart(earningsCtx, {
                type: 'bar',
                data: {
                    labels: monthlyEarnings.map(i => i.month),
                    datasets: [{
                        label: 'Net earnings (AED)',
                        data: monthlyEarnings.map(i => i.amount),
                        backgroundColor: 'rgba(16, 185, 129, 0.75)',
                        borderRadius: 6,
                    }],
                },
                options: {
                    ...vendorChartDefaults,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => 'AED ' + Number(v).toLocaleString() },
                        },
                    },
                },
            });
        }

        const customerGrowth = @json($analytics['customer_growth'] ?? []);
        const customerCtx = document.getElementById('vendorCustomerGrowthChart');
        if (customerCtx && customerGrowth.length) {
            new Chart(customerCtx, {
                type: 'line',
                data: {
                    labels: customerGrowth.map(i => i.month),
                    datasets: [{
                        label: 'Customers',
                        data: customerGrowth.map(i => i.count),
                        borderColor: 'rgba(14, 165, 233, 1)',
                        backgroundColor: 'rgba(14, 165, 233, 0.15)',
                        fill: true,
                        tension: 0.35,
                    }],
                },
                options: {
                    ...vendorChartDefaults,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                },
            });
        }

        const topProducts = @json($analytics['top_products'] ?? []);
        const productsCtx = document.getElementById('vendorProductPerformanceChart');
        if (productsCtx && topProducts.length) {
            new Chart(productsCtx, {
                type: 'bar',
                data: {
                    labels: topProducts.map(i => i.name.length > 22 ? i.name.substring(0, 22) + '…' : i.name),
                    datasets: [{
                        label: 'Revenue (AED)',
                        data: topProducts.map(i => i.revenue),
                        backgroundColor: 'rgba(168, 85, 247, 0.8)',
                        borderRadius: 4,
                    }],
                },
                options: {
                    indexAxis: 'y',
                    ...vendorChartDefaults,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { callback: v => 'AED ' + Number(v).toLocaleString() },
                        },
                    },
                },
            });
        }
    </script>
    @endpush
</x-vendor-layout>
