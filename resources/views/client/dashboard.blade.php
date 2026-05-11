<x-client-layout>
            <!-- Page Header (admin-configured title & subtitle) -->
            <div class="mb-4 sm:mb-6">
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">{{ $dashboardTitle ?? 'My Dashboard' }}</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $dashboardSubtitle ?? "Welcome back! Here's an overview of your subscriptions, visits, and orders." }}</p>
            </div>

            {{-- Banners as compact catalog-style cards (admin promos) --}}
            @if(($showBanners ?? true) && isset($banners) && $banners->count() > 0)
                <div class="mb-4 sm:mb-6 md:mb-8">
                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Updates &amp; offers</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:gap-4 xl:grid-cols-3">
                        @foreach($banners as $banner)
                            @php
                                $accents = [
                                    ['border' => 'hover:border-indigo-400 dark:hover:border-indigo-500', 'chev' => 'group-hover:text-indigo-500 dark:group-hover:text-indigo-400', 'tile' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/35 dark:text-indigo-300', 'ring' => 'ring-indigo-100/80 dark:ring-indigo-800/50'],
                                    ['border' => 'hover:border-teal-400 dark:hover:border-teal-500', 'chev' => 'group-hover:text-teal-500 dark:group-hover:text-teal-400', 'tile' => 'bg-teal-50 text-teal-600 dark:bg-teal-900/35 dark:text-teal-300', 'ring' => 'ring-teal-100/80 dark:ring-teal-800/50'],
                                    ['border' => 'hover:border-violet-400 dark:hover:border-violet-500', 'chev' => 'group-hover:text-violet-500 dark:group-hover:text-violet-400', 'tile' => 'bg-violet-50 text-violet-600 dark:bg-violet-900/35 dark:text-violet-300', 'ring' => 'ring-violet-100/80 dark:ring-violet-800/50'],
                                    ['border' => 'hover:border-amber-400 dark:hover:border-amber-500', 'chev' => 'group-hover:text-amber-500 dark:group-hover:text-amber-400', 'tile' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/35 dark:text-amber-300', 'ring' => 'ring-amber-100/80 dark:ring-amber-800/50'],
                                ];
                                $a = $accents[$loop->index % 4];
                                $hasAction = $banner->action_type !== 'none' && ($banner->action_value || $banner->link);
                                $href = null;
                                if ($banner->action_type === 'link' && $banner->action_value) {
                                    $href = $banner->action_value;
                                } elseif ($banner->action_type === 'route' && $banner->action_value) {
                                    try {
                                        $href = route($banner->action_value);
                                    } catch (\Throwable $e) {
                                        $href = $banner->action_value;
                                    }
                                } elseif ($banner->link) {
                                    $href = $banner->link;
                                }
                                $subtitle = $banner->description
                                    ? \Illuminate\Support\Str::limit(strip_tags($banner->description), 72)
                                    : ($banner->button_text ?: ($hasAction ? 'Tap to open' : 'Announcement'));
                            @endphp
                            @if($href && $hasAction)
                                <a href="{{ $href }}" @if($banner->action_type === 'link' && $banner->action_value) target="_blank" rel="noopener noreferrer" @endif
                                   class="group flex min-w-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-3 shadow-md transition-all duration-200 sm:gap-4 sm:p-4 {{ $a['border'] }} hover:shadow-lg dark:border-gray-600 dark:bg-gray-800">
                            @else
                                <div class="group flex min-w-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-3 shadow-md transition-all duration-200 sm:gap-4 sm:p-4 {{ $a['border'] }} hover:shadow-lg dark:border-gray-600 dark:bg-gray-800">
                            @endif
                                @if($banner->image_url)
                                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-xl ring-2 {{ $a['ring'] }} sm:h-14 sm:w-14">
                                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?: 'Promo' }}" class="h-full w-full object-cover" loading="lazy">
                                    </div>
                                @else
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl sm:h-14 sm:w-14 {{ $a['tile'] }}">
                                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $banner->title ?: 'Announcement' }}</p>
                                    <p class="mt-0.5 text-xs leading-snug text-gray-600 dark:text-gray-400">{{ $subtitle }}</p>
                                </div>
                                @if($href && $hasAction)
                                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors {{ $a['chev'] }} dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                @endif
                            @if($href && $hasAction)
                                </a>
                            @else
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Key Metrics Cards (visibility set by admin) -->
            @if($showMetrics ?? true)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
                <!-- Total Subscriptions Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Subscriptions</p>
                            <p class="text-base sm:text-lg font-medium text-blue-600">{{ number_format($totalSubscriptions ?? 0) }}</p>
                            <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $activeSubscriptions ?? 0 }} active</p>
                        </div>
                        <div class="ml-3 sm:ml-4 flex-shrink-0">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Visits Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Visits</p>
                            <p class="text-base sm:text-lg font-medium text-green-600">{{ number_format($totalVisits ?? 0) }}</p>
                            <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $completedVisits ?? 0 }} completed</p>
                        </div>
                        <div class="ml-3 sm:ml-4 flex-shrink-0">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Orders Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Orders</p>
                            <p class="text-base sm:text-lg font-medium text-purple-600">{{ number_format($totalOrders ?? 0) }}</p>
                            <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $pendingOrders ?? 0 }} pending</p>
                        </div>
                        <div class="ml-3 sm:ml-4 flex-shrink-0">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-purple-50">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Spent Card -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Spent</p>
                            <p class="text-base sm:text-lg font-medium text-amber-600">AED {{ number_format($totalSpent ?? 0, 2) }}</p>
                            <p class="mt-1 sm:mt-2 text-xs text-gray-500">All time</p>
                        </div>
                        <div class="ml-3 sm:ml-4 flex-shrink-0">
                            <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <!-- Secondary Metrics (visibility set by admin) -->
            @if($showSecondaryMetrics ?? true)
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
                <!-- Pending Visits -->
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending Visits</p>
                    <p class="text-xl sm:text-2xl font-semibold text-yellow-600">{{ number_format($pendingVisits ?? 0) }}</p>
                </div>

                <!-- In Progress Visits -->
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">In Progress</p>
                    <p class="text-xl sm:text-2xl font-semibold text-blue-600">{{ number_format($inProgressVisits ?? 0) }}</p>
                </div>

                <!-- Reports -->
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Reports</p>
                    <p class="text-xl sm:text-2xl font-semibold text-indigo-600">{{ number_format($totalReports ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $approvedReports ?? 0 }} approved</p>
                </div>

                <!-- Complaints -->
                <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Complaints</p>
                    <p class="text-xl sm:text-2xl font-semibold text-red-600">{{ number_format($totalComplaints ?? 0) }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $pendingComplaints ?? 0 }} pending</p>
                </div>
            </div>
            @endif

            <!-- Charts Row (visibility set by admin) -->
            @if($showCharts ?? true)
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
                <!-- Visits by Status Chart -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3 sm:mb-4">Visits by Status</h3>
                    <div class="h-48 sm:h-56 md:h-64">
                        <canvas id="visitsStatusChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Spending Chart -->
                <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3 sm:mb-4">Monthly Spending (Last 6 Months)</h3>
                    <div class="h-48 sm:h-56 md:h-64">
                        <canvas id="monthlySpendingChart"></canvas>
                    </div>
                </div>
            </div>
            @endif

            <!-- Recent Subscriptions (visibility set by admin) -->
            @if($showRecentSubscriptions ?? true)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Subscriptions</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plan</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Date</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">End Date</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Visits</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentSubscriptions as $subscription)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                        {{ ucfirst(str_replace('_', ' ', $subscription->plan)) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500">
                                        {{ $subscription->start_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                        {{ $subscription->end_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                        AED {{ number_format($subscription->amount, 2) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            {{ $subscription->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($subscription->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                        {{ $subscription->visits->count() }} / {{ $subscription->total_visits }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No subscriptions found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Recent Visits (visibility set by admin) -->
            @if($showRecentVisits ?? true)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Visits</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled Date</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Technician</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Subscription</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentVisits as $visit)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                        {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                        {{ $visit->technician ? $visit->technician->name : 'Not assigned' }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if($visit->status === 'completed') bg-green-100 text-green-800
                                            @elseif($visit->status === 'started' || $visit->status === 'accepted') bg-blue-100 text-blue-800
                                            @elseif($visit->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucwords(str_replace('_', ' ', $visit->status ?? '')) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                        {{ $visit->subscription ? ucfirst(str_replace('_', ' ', $visit->subscription->plan)) : 'N/A' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No visits found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Recent Reports (visibility set by admin) -->
            @if($showRecentReports ?? true)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Reports</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Supervisor</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Created</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentReports as $report)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                        {{ $report->visit && $report->visit->scheduled_date ? \Carbon\Carbon::parse($report->visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                        {{ $report->supervisor ? $report->supervisor->name : 'N/A' }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if($report->status === 'approved') bg-green-100 text-green-800
                                            @elseif($report->status === 'pending') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst($report->status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                        {{ $report->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No reports found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Recent Orders (visibility set by admin) -->
            @if($showRecentOrders ?? true)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Orders</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Payment</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentOrders as $order)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium text-gray-900">
                                        #{{ $order->id }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                        AED {{ number_format($order->total_amount, 2) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if($order->order_status === 'delivered') bg-green-100 text-green-800
                                            @elseif($order->order_status === 'processing') bg-blue-100 text-blue-800
                                            @else bg-yellow-100 text-yellow-800
                                            @endif">
                                            {{ ucfirst($order->order_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap hidden sm:table-cell">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ ucfirst($order->payment_status) }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                        {{ $order->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No orders found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <!-- Recent Complaints (visibility set by admin) -->
            @if($showRecentComplaints ?? true)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
                <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
                    <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Complaints</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Notes</th>
                                <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($recentComplaints as $complaint)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                        Visit #{{ $complaint->visit_id }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if($complaint->status === 'resolved') bg-green-100 text-green-800
                                            @elseif($complaint->status === 'in_progress') bg-blue-100 text-blue-800
                                            @else bg-yellow-100 text-yellow-800
                                            @endif">
                                            {{ ucfirst($complaint->status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                        {{ Str::limit($complaint->notes ?? 'No notes', 50) }}
                                    </td>
                                    <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                        {{ $complaint->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No complaints found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
    </div>

    @if($showCharts ?? true)
    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Visits by Status Chart
        const visitsStatusCtx = document.getElementById('visitsStatusChart');
        if (visitsStatusCtx) {
            const visitsStatusData = @json($visitsByStatus ?? []);
            const visitsLabels = visitsStatusData.map(item => item.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()));
            const visitsCounts = visitsStatusData.map(item => item.count);

            new Chart(visitsStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: visitsLabels,
                    datasets: [{
                        data: visitsCounts,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(234, 179, 8, 0.8)',
                            'rgba(107, 114, 128, 0.8)',
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Monthly Spending Chart
        const monthlySpendingCtx = document.getElementById('monthlySpendingChart');
        if (monthlySpendingCtx) {
            const monthlySpendingData = @json($monthlySpending ?? []);
            const months = monthlySpendingData.map(item => item.month);
            const amounts = monthlySpendingData.map(item => parseFloat(item.amount || 0));

            new Chart(monthlySpendingCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Spending (AED)',
                        data: amounts,
                        borderColor: 'rgba(59, 130, 246, 1)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5,
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
        }
    </script>
    @endif
</x-client-layout>
