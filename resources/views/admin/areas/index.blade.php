<x-admin-layout>
    <div class="space-y-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-xl font-medium text-gray-900">
                {{ __('admin.areas_management') }}
            </h1>
            <a href="{{ route('admin.zone-assignment.index') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-500">{{ __('admin.zone_assignment') }}</a>
            <a href="{{ route('admin.areas.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                {{ __('admin.new_zone') }}
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.areas.index') }}" class="flex gap-4">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search areas..." class="flex-1 rounded-md border-gray-300">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Search</button>
                <a href="{{ route('admin.areas.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Clear</a>
            </form>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.name') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.country') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.description') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.supervisors') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.technicians') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($areas as $area)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $area->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $area->country ?? 'UAE' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ Str::limit($area->description, 50) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $area->supervisors->count() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $area->technicians->count() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.areas.show', $area->id) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                <a href="{{ route('admin.areas.edit', $area->id) }}" class="text-amber-600 hover:text-amber-900">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('admin.no_areas_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $areas->links() }}
        </div>
    </div>
</x-admin-layout>


