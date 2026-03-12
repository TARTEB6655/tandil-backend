<x-hr-layout>
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

    <!-- Visit Assignments (today / tomorrow: total, assigned, unassigned with clear spacing) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-900">Visit Assignments – Today</h3>
            </div>
            <div class="p-4 sm:p-5 flex flex-wrap gap-3 sm:gap-4">
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg bg-gray-50 border border-gray-100 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-0.5">Total</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $visitAssignments['today']['total'] ?? 0 }}</p>
                </div>
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3">
                    <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide mb-0.5">Assigned</p>
                    <p class="text-2xl font-semibold text-emerald-700">{{ $visitAssignments['today']['assigned'] ?? 0 }}</p>
                </div>
                @php $uToday = $visitAssignments['today']['unassigned'] ?? 0; @endphp
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg {{ $uToday > 0 ? 'bg-red-50 border border-red-200' : 'bg-gray-50 border border-gray-100' }} px-4 py-3">
                    <p class="text-xs font-medium {{ $uToday > 0 ? 'text-red-600' : 'text-gray-500' }} uppercase tracking-wide mb-0.5">Unassigned</p>
                    <p class="text-2xl font-semibold {{ $uToday > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ $uToday }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-base font-semibold text-gray-900">Visit Assignments – Tomorrow</h3>
            </div>
            <div class="p-4 sm:p-5 flex flex-wrap gap-3 sm:gap-4">
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg bg-gray-50 border border-gray-100 px-4 py-3">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-0.5">Total</p>
                    <p class="text-2xl font-semibold text-gray-900">{{ $visitAssignments['tomorrow']['total'] ?? 0 }}</p>
                </div>
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg bg-emerald-50 border border-emerald-100 px-4 py-3">
                    <p class="text-xs font-medium text-emerald-600 uppercase tracking-wide mb-0.5">Assigned</p>
                    <p class="text-2xl font-semibold text-emerald-700">{{ $visitAssignments['tomorrow']['assigned'] ?? 0 }}</p>
                </div>
                @php $uTomorrow = $visitAssignments['tomorrow']['unassigned'] ?? 0; @endphp
                <div class="flex-1 min-w-[calc(33.333%-0.5rem)] sm:min-w-0 rounded-lg {{ $uTomorrow > 0 ? 'bg-red-50 border border-red-200' : 'bg-gray-50 border border-gray-100' }} px-4 py-3">
                    <p class="text-xs font-medium {{ $uTomorrow > 0 ? 'text-red-600' : 'text-gray-500' }} uppercase tracking-wide mb-0.5">Unassigned</p>
                    <p class="text-2xl font-semibold {{ $uTomorrow > 0 ? 'text-red-600' : 'text-gray-700' }}">{{ $uTomorrow }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Leave Requests (profile, role, clickable for full detail) -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6 md:mb-8">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Pending Leave Requests @if(count($pendingLeaveRequests ?? [])) <span class="text-gray-500 font-normal">({{ count($pendingLeaveRequests) }})</span> @endif</h3>
            <a href="{{ route('hr.leave-requests.index', ['status' => 'pending']) }}" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900 font-medium">Manage all</a>
        </div>
        <div class="divide-y divide-gray-200">
            @forelse($pendingLeaveRequests ?? [] as $lr)
                @php
                    $applicant = $lr->user;
                    $applicantId = $applicant?->employee?->employee_id ?? ('EMP-' . $applicant?->id);
                    $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                    $initial = $applicant?->name ? mb_substr(trim($applicant->name), 0, 1) : '?';
                    $avatarUrl = \App\Services\ProfilePictureUploadService::fullUrlOrDefault($applicant?->profile_picture ?? null, $initial);
                    $roleDisplay = $applicant?->role ? ucfirst(str_replace('_', ' ', $applicant->role)) : 'N/A';
                @endphp
                <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50/50 transition-colors">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <a href="{{ route('hr.leave-requests.show', $lr->id) }}" class="flex flex-1 min-w-0 items-start sm:items-center gap-3 group">
                            <img src="{{ $avatarUrl }}" alt="" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover border border-gray-200 flex-shrink-0 ring-1 ring-gray-100">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">{{ $applicant?->name ?? 'N/A' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $applicantId }} · <span class="font-medium text-gray-600">{{ $roleDisplay }}</span></p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ ucfirst($lr->leave_type) }} · {{ $days }} day(s) · From {{ $lr->start_date->format('M d, Y') }}</p>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-indigo-500 flex-shrink-0 mt-0.5 sm:mt-0 sm:ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                        <div class="flex flex-wrap gap-2 flex-shrink-0 sm:pl-3">
                            <form action="{{ route('hr.leave-requests.approve', $lr->id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm">Approve</button>
                            </form>
                            <form action="{{ route('hr.leave-requests.reject', $lr->id) }}" method="POST" class="inline" onsubmit="return confirm('Reject this leave request?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm">Reject</button>
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

    <!-- Staff on leave today (technicians + supervisors – approved leave) -->
    @if(count($staffOnLeaveToday ?? []) > 0)
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 sm:mb-6 md:mb-8">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Staff on leave today</h3>
            <p class="text-xs text-gray-500 mt-0.5">Technicians and supervisors with approved leave for today</p>
        </div>
        <div class="divide-y divide-gray-200">
            @foreach($staffOnLeaveToday as $lr)
                @php
                    $u = $lr->user;
                    $empName = $u?->name ?? 'N/A';
                    $roleDisplay = $u?->role ? ucfirst(str_replace('_', ' ', $u->role)) : 'N/A';
                    $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                @endphp
                <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50/50 transition-colors flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">On Leave</span>
                        <p class="text-sm font-medium text-gray-900">{{ $empName }}</p>
                        <p class="text-xs text-gray-500">{{ $roleDisplay }} · {{ $days }} day(s) · Until {{ $lr->end_date->format('M d, Y') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

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
                @php
                    $empUser = $employee->user;
                    $empName = $employee->name ?? $empUser->name ?? 'N/A';
                    $empPicUrl = $empUser->profile_picture_url ?? null;
                    $empInitial = $empName !== 'N/A' ? mb_substr(trim($empName), 0, 1) : '?';
                    $empLeave = $leaveStatusMap[$employee->user_id ?? 0] ?? ['status' => 'active', 'leave_days' => null, 'leave_remaining_days' => null];
                @endphp
                <div class="px-4 sm:px-6 py-3 sm:py-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-center gap-3">
                        @if($empPicUrl)
                            <img src="{{ $empPicUrl }}" alt="{{ $empName }}" class="w-10 h-10 sm:w-12 sm:h-12 rounded-full object-cover border border-gray-200 flex-shrink-0" />
                        @else
                            <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-emerald-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                {{ mb_strtoupper($empInitial) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $empName }}</p>
                                @if(($empLeave['status'] ?? 'active') === 'on_leave')
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">On Leave</span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $employee->employee_id }} · {{ $employee->designation ?? 'No designation' }}</p>
                            @if($employee->joining_date)
                                <p class="text-xs text-gray-500 mt-0.5">Joined: {{ \Carbon\Carbon::parse($employee->joining_date)->format('Y-m-d') }}</p>
                            @endif
                            @if(($empLeave['status'] ?? '') === 'on_leave' && !empty($empLeave['leave_days']))
                                <p class="text-xs text-gray-600 mt-0.5">Leave: {{ $empLeave['leave_days'] }} days</p>
                            @endif
                        </div>
                        <div class="flex-shrink-0 text-right">
                            <span class="text-xs text-gray-500">{{ $employee->created_at->format('M d, Y') }}</span>
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
