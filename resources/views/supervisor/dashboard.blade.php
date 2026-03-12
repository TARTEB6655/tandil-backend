<x-supervisor-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Supervisor Dashboard</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Welcome back! Here's an overview of visits, reports, and complaints in your supervised areas.</p>
    </div>

    <!-- API-aligned Summary Cards: team_members, active_visits, completed_visits, escalated_jobs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <a href="{{ route('supervisor.team.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md hover:border-indigo-200 transition-all duration-200 block">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">My Team</p>
                    <p class="text-base sm:text-lg font-medium text-indigo-600">{{ number_format($teamMembers ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Technicians in your zones</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-indigo-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Active Visits</p>
                    <p class="text-base sm:text-lg font-medium text-blue-600">{{ number_format($activeVisits ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Pending, scheduled, in progress</p>
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
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completed Visits</p>
                    <p class="text-base sm:text-lg font-medium text-green-600">{{ number_format($completedVisits ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">{{ $completionRatePercent ?? 0 }}% completion rate</p>
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
        <a href="{{ route('supervisor.assign-jobs.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md hover:border-amber-200 transition-all duration-200 block">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Escalated Jobs</p>
                    <p class="text-base sm:text-lg font-medium text-amber-600">{{ number_format($escalatedJobs ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Assign to technician</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('supervisor.leave-requests.index') }}" class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md hover:border-violet-200 transition-all duration-200 block">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Leave</p>
                    <p class="text-base sm:text-lg font-medium text-violet-600">Apply / My requests</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Submit leave for HR approval</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-violet-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- API-aligned Alerts: overdue_visits, upcoming_visits -->
    @if(count($alerts ?? []) > 0)
    <div class="mb-4 sm:mb-6 md:mb-8">
        <h2 class="text-sm font-medium text-gray-700 mb-2">Alerts</h2>
        <div class="space-y-2">
            @foreach($alerts as $alert)
                <div class="flex items-center gap-3 rounded-xl border p-3 sm:p-4
                    @if($alert['type'] === 'overdue_visits') bg-red-50 border-red-200
                    @else bg-amber-50 border-amber-200
                    @endif">
                    @if($alert['type'] === 'overdue_visits')
                        <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    @endif
                    <p class="text-sm font-medium @if($alert['type'] === 'overdue_visits') text-red-800 @else text-amber-800 @endif">{{ $alert['message'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Secondary: Total Visits, Reports, Complaints, KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Visits</p>
            <p class="text-base sm:text-lg font-medium text-gray-700">{{ number_format($totalVisits ?? 0) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $completedVisits ?? 0 }} completed</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Reports</p>
            <p class="text-base sm:text-lg font-medium text-purple-600">{{ number_format($totalReports ?? 0) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $approvedReports ?? 0 }} approved</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Complaints</p>
            <p class="text-base sm:text-lg font-medium text-red-600">{{ number_format($totalComplaints ?? 0) }}</p>
            <p class="mt-1 text-xs text-gray-500">{{ $resolvedComplaints ?? 0 }} resolved</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completed Today</p>
            <p class="text-base sm:text-lg font-medium text-teal-600">{{ number_format($completedToday ?? 0) }}</p>
            <p class="mt-1 text-xs text-gray-500">KPI</p>
        </div>
    </div>

    <!-- Secondary Metrics (KPIs + reports/complaints) -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completion Rate</p>
            <p class="text-xl sm:text-2xl font-semibold text-indigo-600">{{ $completionRatePercent ?? 0 }}%</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending Reports</p>
            <p class="text-xl sm:text-2xl font-semibold text-orange-600">{{ number_format($pendingReports ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Approved Reports</p>
            <p class="text-xl sm:text-2xl font-semibold text-green-600">{{ number_format($approvedReports ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending Complaints</p>
            <p class="text-xl sm:text-2xl font-semibold text-red-600">{{ number_format($pendingComplaints ?? 0) }}</p>
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
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Technician</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
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
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                {{ $visit->technician ? $visit->technician->name : 'Not assigned' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                    @if($visit->status === 'completed') bg-green-100 text-green-800
                                    @elseif($visit->status === 'started') bg-blue-100 text-blue-800
                                    @elseif($visit->status === 'accepted') bg-indigo-100 text-indigo-800
                                    @elseif($visit->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucwords(str_replace('_', ' ', $visit->status ?? '')) }}
                                </span>
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <a href="{{ route('supervisor.visits.show', $visit->id) }}" 
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
                                <a href="{{ route('supervisor.reports.show', $report->id) }}" 
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
                                <a href="{{ route('supervisor.complaints.show', $complaint->id) }}" 
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
</x-supervisor-layout>
