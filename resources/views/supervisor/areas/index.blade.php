<x-supervisor-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div>
            <h1 class="text-lg sm:text-xl font-medium text-gray-900">My Supervised Areas</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">View all areas under your supervision.</p>
        </div>
    </div>

    @if($areas->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Areas Assigned</h3>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">You don't have any areas assigned to supervise yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
            @foreach($areas as $area)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-3 sm:mb-4">
                        <div class="flex-1">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">{{ $area->name }}</h3>
                            @if($area->description)
                                <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ Str::limit($area->description, 100) }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-2 sm:space-y-3 pt-3 sm:pt-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Technicians</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-900">
                                {{ $area->technicians ? $area->technicians->count() : 0 }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-xs text-gray-500">Total Visits</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-900">
                                {{ $area->visits ? $area->visits->count() : 0 }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 sm:mt-6 pt-4 border-t border-gray-200">
                        <a href="{{ route('supervisor.visits.index', ['area' => $area->id]) }}" 
                           class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100">
                            View Visits
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-supervisor-layout>

