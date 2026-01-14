<x-areamanager-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Area Manager Dashboard</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Welcome back! Here's an overview of all areas, visits, and reports across your regions.</p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <!-- Total Areas Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Areas</p>
                    <p class="text-base sm:text-lg font-medium text-indigo-600">{{ number_format($totalAreas ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">All managed areas</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-indigo-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                    <p class="text-base sm:text-lg font-medium text-blue-600">{{ number_format($totalVisits ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $completedVisits ?? 0 }} completed</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Reports Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Reports</p>
                    <p class="text-base sm:text-lg font-medium text-purple-600">{{ number_format($totalReports ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $approvedReports ?? 0 }} approved</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-purple-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Team Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Team</p>
                    <p class="text-base sm:text-lg font-medium text-green-600">{{ number_format(($totalSupervisors ?? 0) + ($totalTechnicians ?? 0)) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $totalSupervisors ?? 0 }} supervisors, {{ $totalTechnicians ?? 0 }} technicians</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Metrics -->
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

        <!-- Completed Visits -->
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completed</p>
            <p class="text-xl sm:text-2xl font-semibold text-green-600">{{ number_format($completedVisits ?? 0) }}</p>
        </div>

        <!-- Pending Reports -->
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending Reports</p>
            <p class="text-xl sm:text-2xl font-semibold text-orange-600">{{ number_format($pendingReports ?? 0) }}</p>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
        <!-- Visits by Status Chart -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Visits by Status</h3>
            <canvas id="visitsByStatusChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>

        <!-- Monthly Visits Chart -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Monthly Visits (Last 6 Months)</h3>
            <canvas id="monthlyVisitsChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- Recent Visits -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900">Recent Visits</h3>
                <a href="{{ route('areamanager.visits.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">View all</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentVisits ?? [] as $visit)
                    <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs sm:text-sm font-medium text-gray-900">
                                    {{ $visit->subscription && $visit->subscription->client ? $visit->subscription->client->name : 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                                </p>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    @if($visit->status === 'completed') bg-green-100 text-green-800
                                    @elseif($visit->status === 'started') bg-blue-100 text-blue-800
                                    @elseif($visit->status === 'accepted') bg-indigo-100 text-indigo-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ ucfirst($visit->status) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 sm:px-6 py-8 text-center">
                        <p class="text-xs sm:text-sm text-gray-500">No recent visits</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Reports -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-base sm:text-lg font-semibold text-gray-900">Recent Reports</h3>
                <a href="{{ route('areamanager.reports.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">View all</a>
            </div>
            <div class="divide-y divide-gray-200">
                @forelse($recentReports ?? [] as $report)
                    <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50 transition-colors">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs sm:text-sm font-medium text-gray-900">
                                    {{ $report->visit && $report->visit->subscription && $report->visit->subscription->client ? $report->visit->subscription->client->name : 'N/A' }}
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ $report->created_at->format('M d, Y') }}
                                </p>
                            </div>
                            <div class="ml-4 flex-shrink-0">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    @if($report->status === 'approved') bg-green-100 text-green-800
                                    @else bg-yellow-100 text-yellow-800
                                    @endif">
                                    {{ ucfirst($report->status ?? 'pending') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-4 sm:px-6 py-8 text-center">
                        <p class="text-xs sm:text-sm text-gray-500">No recent reports</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Chart Scripts -->
    <script>
        // Visits by Status Chart
        const visitsByStatusCtx = document.getElementById('visitsByStatusChart');
        if (visitsByStatusCtx) {
            new Chart(visitsByStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode($visitsByStatusLabels ?? array_keys($visitsByStatus ?? [])) !!},
                    datasets: [{
                        data: {!! json_encode($visitsByStatusData ?? array_values($visitsByStatus ?? [])) !!},
                        backgroundColor: [
                            '#fbbf24', // yellow for pending
                            '#8b5cf6', // purple for accepted
                            '#3b82f6', // blue for started
                            '#10b981', // green for completed
                            '#059669'  // darker green for approved
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Monthly Visits Chart
        const monthlyVisitsCtx = document.getElementById('monthlyVisitsChart');
        if (monthlyVisitsCtx) {
            const monthlyData = {!! json_encode($monthlyVisits ?? []) !!};
            new Chart(monthlyVisitsCtx, {
                type: 'line',
                data: {
                    labels: monthlyData.map(item => item.month),
                    datasets: [{
                        label: 'Visits',
                        data: monthlyData.map(item => item.count),
                        borderColor: '#8b5cf6',
                        backgroundColor: 'rgba(139, 92, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
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
        }
    </script>
</x-areamanager-layout>
