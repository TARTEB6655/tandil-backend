<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6 flex items-start sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg sm:text-xl font-medium text-gray-900">My Visits</h1>
            <p class="mt-1 text-xs sm:text-sm text-gray-500">Track all your scheduled and completed visits.</p>
        </div>
        <a href="{{ route('client.visits.create') }}" class="px-3 py-2 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700">Create Job</a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <!-- Visits Table -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-medium text-gray-900">All Visits</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 min-w-[640px]">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scheduled Date</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden sm:table-cell">Technician</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">Supervisor</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-3 sm:px-6 py-2 sm:py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($visits as $visit)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-900">
                                {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden sm:table-cell">
                                {{ $visit->technician ? $visit->technician->name : 'Not assigned' }}
                            </td>
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm text-gray-500 hidden md:table-cell">
                                {{ $visit->supervisor ? $visit->supervisor->name : 'Not assigned' }}
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
                            <td class="px-3 sm:px-6 py-3 sm:py-4 whitespace-nowrap text-xs sm:text-sm font-medium">
                                <a href="{{ route('client.visits.show', $visit->id) }}" 
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
        @if($visits->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200">
                {{ $visits->links() }}
            </div>
        @endif
    </div>
</x-client-layout>

