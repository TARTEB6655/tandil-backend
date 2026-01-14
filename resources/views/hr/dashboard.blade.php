<x-hr-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">HR Dashboard</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Welcome back! Manage employees and track workforce statistics.</p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-4 sm:mb-6 md:mb-8">
        <!-- Total Employees Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Employees</p>
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

        <!-- Technicians Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Technicians</p>
                    <p class="text-base sm:text-lg font-medium text-blue-600">{{ number_format($totalTechnicians ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Active technicians</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-blue-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Supervisors Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Supervisors</p>
                    <p class="text-base sm:text-lg font-medium text-green-600">{{ number_format($totalSupervisors ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Active supervisors</p>
                </div>
                <div class="ml-3 sm:ml-4 flex-shrink-0">
                    <div class="flex h-9 w-9 sm:h-10 sm:w-10 md:h-12 md:w-12 items-center justify-center rounded-xl bg-green-50">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 md:w-6 md:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Area Managers Card -->
        <div class="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200">
            <div class="flex items-center justify-between">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Area Managers</p>
                    <p class="text-base sm:text-lg font-medium text-purple-600">{{ number_format($totalAreaManagers ?? 0) }}</p>
                    <p class="mt-1 sm:mt-2 text-xs text-gray-500">Active area managers</p>
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

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6 md:mb-8">
        <!-- Employees by Designation Chart -->
        @if(!empty($employeesByDesignation))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-4">Employees by Designation</h3>
            <canvas id="designationChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>
        @endif

        <!-- Employees by Region Chart -->
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
                                {{ $employee->employee_id }} • {{ $employee->designation ?? 'No designation' }}
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

    <!-- Chart Scripts -->
    <script>
        // Employees by Designation Chart
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
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
        @endif

        // Employees by Region Chart
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
        @endif
    </script>
</x-hr-layout>
