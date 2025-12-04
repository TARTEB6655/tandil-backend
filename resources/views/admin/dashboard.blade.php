@php
    use App\Models\User;
    use App\Models\Order;
    use App\Models\Complaint;
    
    // Additional calculations
    $totalOrders = Order::count();
    $ordersToday = Order::whereDate('created_at', \Carbon\Carbon::today())->count();
    $recentUsers = User::latest()->take(5)->get();
@endphp

<x-admin-layout>
    <!-- Page Header -->
    <div class="mb-6 md:mb-8">
        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Dashboard Overview</h1>
        <p class="mt-1 text-sm md:text-base text-gray-600">Welcome back! Here's what's happening with your business today.</p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Total Users Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Users</p>
                    <p class="text-2xl md:text-3xl font-bold text-gray-900">{{ number_format($totalUsers ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">All registered users</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-12 w-12 md:h-14 md:w-14 items-center justify-center rounded-xl bg-blue-50">
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="text-2xl md:text-3xl font-bold text-gray-900">{{ number_format($activeSubscriptions ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">Currently active</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-12 w-12 md:h-14 md:w-14 items-center justify-center rounded-xl bg-green-50">
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="text-2xl md:text-3xl font-bold text-gray-900">AED {{ number_format($totalRevenue ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">All time revenue</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-12 w-12 md:h-14 md:w-14 items-center justify-center rounded-xl bg-amber-50">
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <p class="text-2xl md:text-3xl font-bold text-gray-900">{{ number_format($totalOrders ?? 0) }}</p>
                    <p class="mt-2 text-xs text-gray-500">{{ $ordersToday ?? 0 }} today</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <div class="flex h-12 w-12 md:h-14 md:w-14 items-center justify-center rounded-xl bg-purple-50">
                        <svg class="w-6 h-6 md:w-7 md:h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-6 md:mb-8">
        <!-- Today's Visits -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Today's Visits</p>
                    <p class="text-xl md:text-2xl font-bold text-gray-900">{{ $visitsToday ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Pending Complaints -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 md:p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Pending Complaints</p>
                    <p class="text-xl md:text-2xl font-bold text-gray-900">{{ $pendingComplaints ?? 0 }}</p>
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
                    <p class="text-xl md:text-2xl font-bold text-gray-900">{{ $pendingReports ?? 0 }}</p>
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
                    <p class="text-xl md:text-2xl font-bold text-gray-900">{{ $activeRegions ?? 0 }}</p>
                </div>
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-50">
                    <svg class="w-5 h-5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Revenue Growth Chart -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Revenue Growth</h3>
                <p class="text-sm text-gray-500 mt-1">Monthly revenue over the last 6 months</p>
            </div>
            <div class="h-64 md:h-80 flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600">Chart Placeholder</p>
                    <p class="text-xs text-gray-500 mt-1">Replace with Chart.js or similar library</p>
                </div>
            </div>
        </div>

        <!-- User Activity Chart -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-lg font-semibold text-gray-900">User Activity</h3>
                <p class="text-sm text-gray-500 mt-1">Weekly visits over the last 8 weeks</p>
            </div>
            <div class="h-64 md:h-80 flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600">Chart Placeholder</p>
                    <p class="text-xs text-gray-500 mt-1">Replace with Chart.js or similar library</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Distribution Chart Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Subscription Distribution -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Subscription Distribution</h3>
                <p class="text-sm text-gray-500 mt-1">Active subscriptions by plan type</p>
            </div>
            <div class="h-64 md:h-80 flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600">Pie/Donut Chart Placeholder</p>
                    <p class="text-xs text-gray-500 mt-1">Replace with Chart.js or similar library</p>
                </div>
            </div>
        </div>

        <!-- Visit Status Distribution -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6">
                <h3 class="text-lg font-semibold text-gray-900">Visit Status</h3>
                <p class="text-sm text-gray-500 mt-1">Distribution of visits by status</p>
            </div>
            <div class="h-64 md:h-80 flex items-center justify-center bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-600">Donut Chart Placeholder</p>
                    <p class="text-xs text-gray-500 mt-1">Replace with Chart.js or similar library</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8 mb-6 md:mb-8">
        <!-- Recent Users -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Recent Users</h3>
                    <p class="text-sm text-gray-500 mt-1">Latest registered users</p>
                </div>
                <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all</a>
            </div>
            <div class="space-y-3">
                @forelse($recentUsers as $user)
                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 text-white text-sm font-semibold">
                            {{ strtoupper(substr($user->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No users yet</p>
                @endforelse
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Recent Orders</h3>
                    <p class="text-sm text-gray-500 mt-1">Latest order activity</p>
                </div>
                <a href="{{ route('admin.orders.index') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all</a>
            </div>
            <div class="space-y-3">
                @forelse(($recentOrders ?? collect())->take(5) as $order)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">Order #{{ $order->id ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-500">AED {{ number_format($order->total_amount ?? 0, 2) }}</p>
                        </div>
                        <span class="ml-3 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            {{ ucfirst($order->order_status ?? 'Pending') }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No orders yet</p>
                @endforelse
            </div>
        </div>

        <!-- Recent Complaints -->
        <div class="bg-white rounded-xl border border-gray-200 p-5 md:p-6 shadow-sm">
            <div class="mb-4 md:mb-6 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Recent Complaints</h3>
                    <p class="text-sm text-gray-500 mt-1">Latest customer complaints</p>
                </div>
                <a href="{{ route('admin.complaints.index') }}" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all</a>
            </div>
            <div class="space-y-3">
                @php
                    $recentComplaints = \App\Models\Complaint::latest()->take(5)->get();
                @endphp
                @forelse($recentComplaints as $complaint)
                    <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ Str::limit($complaint->subject ?? 'No subject', 30) }}</p>
                            <p class="text-xs text-gray-500">{{ $complaint->created_at->diffForHumans() ?? 'Recently' }}</p>
                        </div>
                        <span class="ml-3 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium {{ $complaint->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                            {{ ucfirst($complaint->status ?? 'Pending') }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">No complaints yet</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
