<x-areamanager-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">{{ $area->name }}</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Area details and statistics.</p>
            </div>
            <a href="{{ route('areamanager.areas.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Areas
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-4 sm:mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Visits</p>
            <p class="text-xl sm:text-2xl font-semibold text-blue-600">{{ number_format($totalVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Pending</p>
            <p class="text-xl sm:text-2xl font-semibold text-yellow-600">{{ number_format($pendingVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Completed</p>
            <p class="text-xl sm:text-2xl font-semibold text-green-600">{{ number_format($completedVisits ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Supervisors</p>
            <p class="text-xl sm:text-2xl font-semibold text-purple-600">{{ number_format($totalSupervisors ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-3 sm:p-4 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Technicians</p>
            <p class="text-xl sm:text-2xl font-semibold text-indigo-600">{{ number_format($totalTechnicians ?? 0) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- Area Information -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Area Information</h2>
            <div class="space-y-3 sm:space-y-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Name</p>
                    <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $area->name }}</p>
                </div>
                @if($area->description)
                <div>
                    <p class="text-xs text-gray-500 mb-1">Description</p>
                    <p class="text-xs sm:text-sm text-gray-700">{{ $area->description }}</p>
                </div>
                @endif
            </div>
        </div>

        <!-- Supervisors -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Supervisors ({{ $area->supervisors ? $area->supervisors->count() : 0 }})</h2>
            @if($area->supervisors && $area->supervisors->count() > 0)
                <div class="space-y-2">
                    @foreach($area->supervisors as $supervisor)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $supervisor->name }}</p>
                                <p class="text-xs text-gray-500">{{ $supervisor->email }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs sm:text-sm text-gray-500">No supervisors assigned to this area.</p>
            @endif
        </div>

        <!-- Technicians -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Technicians ({{ $area->technicians ? $area->technicians->count() : 0 }})</h2>
            @if($area->technicians && $area->technicians->count() > 0)
                <div class="space-y-2">
                    @foreach($area->technicians as $technician)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                            <div>
                                <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $technician->name }}</p>
                                <p class="text-xs text-gray-500">{{ $technician->email }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-xs sm:text-sm text-gray-500">No technicians assigned to this area.</p>
            @endif
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
            <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Quick Actions</h2>
            <div class="space-y-2">
                <a href="{{ route('areamanager.visits.index', ['area' => $area->id]) }}" 
                   class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    View Visits
                </a>
                <a href="{{ route('areamanager.reports.index', ['area' => $area->id]) }}" 
                   class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-purple-600 bg-purple-50 rounded-lg hover:bg-purple-100">
                    View Reports
                </a>
            </div>
        </div>
    </div>
</x-areamanager-layout>

