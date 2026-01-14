<x-areamanager-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div>
            <h1 class="text-lg sm:text-xl font-medium text-gray-900">All Areas</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">View and manage all areas in the system.</p>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="mb-4 sm:mb-6">
        <form action="{{ route('areamanager.areas.index') }}" method="GET" class="w-full">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" 
                       name="search" 
                       value="{{ $search ?? '' }}" 
                       placeholder="Search areas..."
                       class="w-full pl-10 pr-10 py-2 text-xs sm:text-sm bg-gray-50 border border-gray-200 rounded-lg
                              focus:outline-none focus:ring-1 focus:ring-gray-300 focus:border-gray-300 focus:bg-white
                              transition-all duration-200">
                @if($search ?? '')
                    <button type="button"
                            onclick="window.location.href='{{ route('areamanager.areas.index') }}'"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition-colors duration-200"
                            aria-label="Clear search">
                        <svg class="h-4 w-4 sm:h-5 sm:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
            </div>
        </form>
    </div>

    @if($areas->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No Areas Found</h3>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">No areas match your search criteria.</p>
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
                            <span class="text-xs text-gray-500">Supervisors</span>
                            <span class="text-xs sm:text-sm font-medium text-gray-900">
                                {{ $area->supervisors ? $area->supervisors->count() : 0 }}
                            </span>
                        </div>
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
                        <a href="{{ route('areamanager.areas.show', $area->id) }}" 
                           class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100">
                            View Details
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        @if(method_exists($areas, 'hasPages') && $areas->hasPages())
            <div class="mt-4 sm:mt-6">
                {{ $areas->links() }}
            </div>
        @endif
    @endif
</x-areamanager-layout>

