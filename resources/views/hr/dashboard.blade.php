<x-hr-layout>
    @php
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp

    <!-- Welcome / Profile header (aligned with API) -->
    <div class="mb-4 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <p class="text-sm text-gray-500">{{ $greeting }}!</p>
            <h1 class="text-lg sm:text-xl font-semibold text-gray-900 mt-0.5">{{ $user->name ?? 'HR User' }}</h1>
            <p class="text-xs sm:text-sm text-gray-500 mt-0.5">HR Manager · ID: {{ $hrId ?? 'HR-' }}</p>
        </div>
    </div>

    <!-- Key metrics (aligned with API: total_staff, new_hires, leave_requests) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Staff</p>
                    <p class="text-base sm:text-lg font-medium text-pink-600">{{ number_format($totalEmployees ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">All employee records</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-pink-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">New Hires</p>
                    <p class="text-base sm:text-lg font-medium text-blue-600">{{ number_format($newHires ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Last 30 days</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Leave Requests</p>
                    <p class="text-base sm:text-lg font-medium text-amber-600">{{ number_format($leaveRequestsCount ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Pending approval</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-amber-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Area Managers</p>
                    <p class="text-base sm:text-lg font-medium text-purple-600">{{ number_format($totalAreaManagers ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Active</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-purple-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Visit Assignments (aligned with API: today / tomorrow) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Visit Assignments – Today</h3>
            <p class="text-2xl font-medium text-gray-900">{{ $visitAssignments['today']['total'] ?? 0 }} total</p>
            <p class="text-sm text-gray-600 mt-1">{{ $visitAssignments['today']['assigned'] ?? 0 }} assigned</p>
            <p class="text-sm {{ ($visitAssignments['today']['unassigned'] ?? 0) > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ $visitAssignments['today']['unassigned'] ?? 0 }} unassigned</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-3">Visit Assignments – Tomorrow</h3>
            <p class="text-2xl font-medium text-gray-900">{{ $visitAssignments['tomorrow']['total'] ?? 0 }} total</p>
            <p class="text-sm text-gray-600 mt-1">{{ $visitAssignments['tomorrow']['assigned'] ?? 0 }} assigned</p>
            <p class="text-sm {{ ($visitAssignments['tomorrow']['unassigned'] ?? 0) > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">{{ $visitAssignments['tomorrow']['unassigned'] ?? 0 }} unassigned</p>
        </div>
    </div>

    <!-- Pending Leave Requests (aligned with API; approve/reject from dashboard) -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6 md:mb-8">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Pending Leave Requests @if(count($pendingLeaveRequests ?? [])) <span class="text-gray-500 font-normal">({{ count($pendingLeaveRequests) }})</span> @endif</h3>
            <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">Manage all</a>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($pendingLeaveRequests ?? [] as $lr)
                @php
                    $applicant = $lr->user;
                    $applicantId = $applicant?->employee?->employee_id ?? ('EMP-' . $applicant?->id);
                    $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                @endphp
                <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ $applicant?->name ?? 'N/A' }}</p>
                            <p class="text-xs text-gray-500">{{ $applicantId }} · {{ $lr->leave_type }} · {{ $days }} day(s)</p>
                            <p class="text-xs text-gray-500 mt-0.5">From {{ $lr->start_date->format('Y-m-d') }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 flex-shrink-0">
                            <form action="{{ route('hr.leave-requests.approve', $lr->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700">Approve</button>
                            </form>
                            <form action="{{ route('hr.leave-requests.reject', $lr->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject this leave request?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">Reject</button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 sm:px-6 py-8 text-center">
                    <p class="text-sm text-gray-500">No pending leave requests.</p>
                    <a href="{{ route('hr.leave-requests.index') }}" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-900">Manage leaves</a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
        @if(!empty($employeesByDesignation))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Employees by Designation</h3>
            <canvas id="designationChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
        @endif
        @if(!empty($employeesByRegion))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Employees by Region</h3>
            <canvas id="regionChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
        @endif
    </div>

    <!-- Recent Employees -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Recent Employees</h3>
            <a href="{{ route('hr.employees.index') }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900">View all</a>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($recentEmployees ?? [] as $employee)
                <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-900">
                                {{ $employee->user ? $employee->user->name : 'N/A' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                {{ $employee->employee_id }} · {{ $employee->designation ?? 'No designation' }}
                            </p>
                        </div>
                        <div class="ml-4 flex-shrink-0">
                            <span class="text-xs text-gray-500">
                                {{ $employee->created_at->format('M d, Y') }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="px-4 sm:px-6 py-8 text-center">
                    <p class="text-xs sm:text-sm text-gray-500">No employees found</p>
                </div>
            @endforelse
        </div>
    </div>

    @if(!empty($employeesByDesignation) || !empty($employeesByRegion))
    <script>
        @if(!empty($employeesByDesignation))
        const designationCtx = document.getElementById('designationChart');
        if (designationCtx) {
            new Chart(designationCtx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode(array_keys($employeesByDesignation)) !!},
                    datasets: [{
                        data: {!! json_encode(array_values($employeesByDesignation)) !!},
                        backgroundColor: [
                            '#ec4899', '#8b5cf6', '#3b82f6', '#10b981', '#f59e0b'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
        @endif
        @if(!empty($employeesByRegion))
        const regionCtx = document.getElementById('regionChart');
        if (regionCtx) {
            new Chart(regionCtx, {
                type: 'bar',
                data: {
                    labels: {!! json_encode(array_keys($employeesByRegion)) !!},
                    datasets: [{
                        label: 'Employees',
                        data: {!! json_encode(array_values($employeesByRegion)) !!},
                        backgroundColor: '#ec4899',
                        borderColor: '#db2777',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }
        @endif
    </script>
    @endif
</x-hr-layout>
