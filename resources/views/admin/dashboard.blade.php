@php
    use App\Models\Order;
    
    // Additional calculations
    $totalOrders = Order::count();
    $ordersToday = Order::whereDate('created_at', \Carbon\Carbon::today())->count();
@endphp

<x-admin-layout>
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-medium text-gray-900">Dashboard Overview</h1>
        <p class="mt-1 text-sm text-gray-500">Welcome back! Here's what's happening with your business today.</p>
    </div>

    <!-- Info Message -->
    @if(session('info'))
        <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm">{{ session('info') }}</span>
        </div>
    @endif

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Total Users Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Users</p>
                    <p class="text-lg font-medium text-blue-600">{{ number_format($totalUsers ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">All registered users</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Subscriptions Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Active Subscriptions</p>
                    <p class="text-lg font-medium text-green-600">{{ number_format($activeSubscriptions ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">Currently active</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Revenue Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Revenue</p>
                    <p class="text-lg font-medium text-amber-600">AED {{ number_format($totalRevenue ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">All time revenue</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Orders Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Orders</p>
                    <p class="text-lg font-medium text-purple-600">{{ number_format($totalOrders ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $ordersToday ?? 0 }} today</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-purple-50">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- E-Commerce Section -->
    <div class="mb-6 md:mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-medium text-gray-900">E-Commerce Overview</h2>
            <a href="{{ route('admin.orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">View All Orders →</a>
        </div>

        <!-- E-Commerce Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6">
            <!-- Paid Orders -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Paid Orders</p>
                        <p class="text-lg font-medium text-green-600">{{ number_format($paidOrders ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ number_format(($paidOrders ?? 0) / max($totalOrders ?? 1, 1) * 100, 1) }}% of total</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-green-50 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Pending Payments -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending Payments</p>
                        <p class="text-lg font-medium text-yellow-600">{{ number_format($pendingPayments ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500">Awaiting payment</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-yellow-50 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Revenue This Month -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Revenue (Month)</p>
                        <p class="text-lg font-medium text-indigo-600">AED {{ number_format($revenueThisMonth ?? 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500">This month</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Total Products -->
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Products</p>
                        <p class="text-lg font-medium text-blue-600">{{ number_format($totalProducts ?? 0) }}</p>
                        <p class="mt-1 text-xs text-red-600">{{ $lowStockProducts ?? 0 }} low stock</p>
                    </div>
                    <div class="h-10 w-10 md:h-12 md:w-12 rounded-xl bg-blue-50 flex items-center justify-center">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Order Status Breakdown -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900 mb-4">Order Status</h3>
                <div class="space-y-3">
                    @foreach($ordersByStatus ?? [] as $status)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    {{ $status->order_status === 'delivered' ? 'bg-green-100 text-green-800' : 
                                       ($status->order_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                       ($status->order_status === 'shipped' ? 'bg-purple-100 text-purple-800' : 
                                       ($status->order_status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))) }}">
                                    {{ ucfirst($status->order_status) }}
                                </span>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">{{ $status->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Payment Status Breakdown -->
            <div class="bg-white rounded-xl border border-gray-200 p-6 shadow-sm">
                <h3 class="text-base font-medium text-gray-900 mb-4">Payment Status</h3>
                <div class="space-y-3">
                    @foreach($ordersByPaymentStatus ?? [] as $payment)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    {{ $payment->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                       ($payment->payment_status === 'failed' ? 'bg-red-100 text-red-800' : 
                                       ($payment->payment_status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                    {{ ucfirst($payment->payment_status) }}
                                </span>
                            </div>
                            <span class="text-sm font-medium text-indigo-600">{{ $payment->count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    <!-- Secondary Metrics Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Total Visits -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Total Visits</p>
                    <p class="text-lg font-medium text-indigo-600">{{ number_format($totalVisits ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $visitsToday ?? 0 }} today</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <!-- Today's Visits -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Today's Visits</p>
                    <p class="text-lg font-medium text-indigo-600">{{ $visitsToday ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Scheduled today</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Complaints -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Pending Complaints</p>
                    <p class="text-lg font-medium text-red-600">{{ $pendingComplaints ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Reports -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Pending Reports</p>
                    <p class="text-lg font-medium text-yellow-600">{{ $pendingReports ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-50">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Active Regions -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Active Regions</p>
                    <p class="text-lg font-medium text-teal-600">{{ $activeRegions ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Technician Performance Summary -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Technician Performance</h3>
                <p class="text-sm text-gray-500 mt-1">Top 10 technicians by completed visits</p>
            </div>
            <div class="space-y-3">
                @forelse($technicianPerformance ?? [] as $technician)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white text-sm font-medium">
                                {{ strtoupper(substr($technician->name, 0, 1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $technician->name }}</p>
                                <p class="text-xs text-gray-500">{{ $technician->email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-blue-600">{{ $technician->visits_count ?? 0 }}</p>
                            <p class="text-xs text-gray-500">visits</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No technician data available</p>
                @endforelse
            </div>
        </div>

        <!-- Area Performance Summary -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Area Performance</h3>
                <p class="text-sm text-gray-500 mt-1">Visits by area/region</p>
            </div>
            <div class="space-y-3">
                @forelse($areaPerformance ?? [] as $area)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-green-500 to-teal-600 text-white">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $area->name }}</p>
                                <p class="text-xs text-gray-500">{{ $area->city ?? 'N/A' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-green-600">{{ $area->visits_count ?? 0 }}</p>
                            <p class="text-xs text-gray-500">visits</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No area data available</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Revenue Growth Chart -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Revenue Growth</h3>
                <p class="text-sm text-gray-500 mt-1">Monthly revenue over the last 6 months</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        <!-- Visits Activity Chart -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Visits Activity</h3>
                <p class="text-sm text-gray-500 mt-1">Weekly visits over the last 8 weeks</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Distribution Chart Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Subscription Distribution -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Subscription Distribution</h3>
                <p class="text-sm text-gray-500 mt-1">Active subscriptions by plan type</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="subscriptionsChart"></canvas>
            </div>
        </div>

        <!-- Visit Status Distribution -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-base font-medium text-gray-900">Visit Status</h3>
                <p class="text-sm text-gray-500 mt-1">Distribution of visits by status</p>
            </div>
            <div class="h-64 md:h-80">
                <canvas id="visitStatusChart"></canvas>
            </div>
        </div>
    </div>


    <!-- Chart.js Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Revenue Chart
            fetch('{{ route("admin.analytics.revenue") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.revenue || data.revenue.length === 0) {
                        console.warn('No revenue data available');
                        // Show empty state
                        const ctx = document.getElementById('revenueChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: ['No Data'],
                                    datasets: [{
                                        label: 'Revenue (AED)',
                                        data: [0],
                                        borderColor: 'rgb(99, 102, 241)',
                                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Revenue (AED)',
                                data: data.revenue,
                                borderColor: 'rgb(99, 102, 241)',
                                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        callback: function(value) {
                                            return 'AED ' + value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading revenue chart:', error);
                });

            // Visits Chart
            fetch('{{ route("admin.analytics.visits") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0) {
                        console.warn('No visits data available');
                        const ctx = document.getElementById('visitsChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: ['No Data'],
                                    datasets: [{
                                        label: 'Visits',
                                        data: [0],
                                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { display: false } }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('visitsChart'), {
                        type: 'bar',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                label: 'Visits',
                                data: data.counts,
                                backgroundColor: 'rgba(16, 185, 129, 0.8)',
                                borderColor: 'rgb(16, 185, 129)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading visits chart:', error);
                });

            // Subscriptions Chart
            fetch('{{ route("admin.analytics.subscriptions") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0 || (data.counts.length === 1 && data.counts[0] === 0)) {
                        console.warn('No subscriptions data available');
                        const ctx = document.getElementById('subscriptionsChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: ['No Active Subscriptions'],
                                    datasets: [{
                                        data: [1],
                                        backgroundColor: ['rgba(156, 163, 175, 0.5)']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('subscriptionsChart'), {
                        type: 'doughnut',
                        data: {
                            labels: data.labels,
                            datasets: [{
                                data: data.counts,
                                backgroundColor: [
                                    'rgba(99, 102, 241, 0.8)',
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)',
                                    'rgba(139, 92, 246, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading subscriptions chart:', error);
                });

            // Visit Status Chart
            fetch('{{ route("admin.analytics.visit-status") }}')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data.labels || data.labels.length === 0 || !data.counts || data.counts.length === 0) {
                        console.warn('No visit status data available');
                        const ctx = document.getElementById('visitStatusChart');
                        if (ctx) {
                            new Chart(ctx, {
                                type: 'pie',
                                data: {
                                    labels: ['No Visits'],
                                    datasets: [{
                                        data: [1],
                                        backgroundColor: ['rgba(156, 163, 175, 0.5)']
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { position: 'bottom' }
                                    }
                                }
                            });
                        }
                        return;
                    }
                    new Chart(document.getElementById('visitStatusChart'), {
                        type: 'pie',
                        data: {
                            labels: data.labels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                            datasets: [{
                                data: data.counts,
                                backgroundColor: [
                                    'rgba(16, 185, 129, 0.8)',
                                    'rgba(99, 102, 241, 0.8)',
                                    'rgba(245, 158, 11, 0.8)',
                                    'rgba(239, 68, 68, 0.8)',
                                    'rgba(139, 92, 246, 0.8)'
                                ]
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading visit status chart:', error);
                });
        });
    </script>
</x-admin-layout>
