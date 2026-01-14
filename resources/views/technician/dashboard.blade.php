<x-technician-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Technician Dashboard</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Welcome back! Here's an overview of your assigned visits, reports, and tasks.</p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
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

        <!-- Completed Visits Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completed</p>
                    <p class="text-base sm:text-lg font-medium text-green-600">{{ number_format($completedVisits ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $totalVisits > 0 ? round(($completedVisits / $totalVisits) * 100) : 0 }}% completion rate</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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

        <!-- Complaints Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Complaints</p>
                    <p class="text-base sm:text-lg font-medium text-red-600">{{ number_format($totalComplaints ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $resolvedComplaints ?? 0 }} resolved</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-red-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
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
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending</p>
            <p class="text-xl sm:text-2xl font-semibold text-yellow-600">{{ number_format($pendingVisits ?? 0) }}</p>
        </div>

        <!-- Accepted Visits -->
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Accepted</p>
            <p class="text-xl sm:text-2xl font-semibold text-indigo-600">{{ number_format($acceptedVisits ?? 0) }}</p>
        </div>

        <!-- In Progress -->
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">In Progress</p>
            <p class="text-xl sm:text-2xl font-semibold text-blue-600">{{ number_format($inProgressVisits ?? 0) }}</p>
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
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3 sm:mb-4">Visits by Status</h3>
            <div class="h-48 sm:h-56 md:h-64">
                <canvas id="visitsStatusChart"></canvas>
            </div>
        </div>

        <!-- Monthly Visits Chart -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-6 shadow-sm">
            <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-3 sm:mb-4">Monthly Visits (Last 6 Months)</h3>
            <div class="h-48 sm:h-56 md:h-64">
                <canvas id="monthlyVisitsChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Visits -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Visits</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled Date</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Client</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Area</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recentVisits as $visit)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                {{ $visit->subscription && $visit->subscription->client ? $visit->subscription->client->name : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    @if($visit->status === 'completed') bg-green-100 text-green-800
                                    @elseif($visit->status === 'started') bg-blue-100 text-blue-800
                                    @elseif($visit->status === 'accepted') bg-indigo-100 text-indigo-800
                                    @elseif($visit->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($visit->status) }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                {{ $visit->area ? $visit->area->name : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <a href="{{ route('technician.visits.show', $visit->id) }}" 
                                   class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No visits found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">Recent Reports</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Client</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Created</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($recentReports as $report)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                {{ $report->visit && $report->visit->scheduled_date ? \Carbon\Carbon::parse($report->visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                {{ $report->visit && $report->visit->subscription && $report->visit->subscription->client ? $report->visit->subscription->client->name : 'N/A' }}
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
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <a href="{{ route('technician.reports.show', $report->id) }}" 
                                   class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No reports found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Complaints -->
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
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <a href="{{ route('technician.complaints.show', $complaint->id) }}" 
                                   class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 sm:px-6 py-4 text-center text-xs sm:text-sm text-gray-500">No complaints found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    // Visits by Status Chart
    const visitsStatusCtx = document.getElementById('visitsStatusChart');
    if (visitsStatusCtx) {
        const visitsStatusData = @json($visitsByStatus ?? []);
        const visitsLabels = visitsStatusData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1));
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
                        'rgba(99, 102, 241, 0.8)',
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

    // Monthly Visits Chart
    const monthlyVisitsCtx = document.getElementById('monthlyVisitsChart');
    if (monthlyVisitsCtx) {
        const monthlyVisitsData = @json($monthlyVisits ?? []);
        const months = monthlyVisitsData.map(item => item.month);
        const counts = monthlyVisitsData.map(item => parseInt(item.count || 0));

        new Chart(monthlyVisitsCtx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Visits',
                    data: counts,
                    borderColor: 'rgba(16, 185, 129, 1)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
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
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }
</script>
</x-technician-layout>
