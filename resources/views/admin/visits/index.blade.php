<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-3 mb-6">
            <h1 class="text-xl font-medium text-gray-900">
                {{ __('admin.visits_management') }}
            </h1>
            <a href="{{ route('admin.visits.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                Create Job
            </a>
        </div>
        <!-- Filters -->
        <div class="bg-white shadow rounded-lg p-4 mb-6">
            <form method="GET" action="{{ route('admin.visits.index') }}" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('admin.search_by_client') }}" class="w-full rounded-md border-gray-300 xl:col-span-2">
                <select name="status" class="w-full rounded-md border-gray-300">
                    <option value="">{{ __('admin.all_statuses') }}</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('admin.pending') }}</option>
                    <option value="accepted" {{ request('status') == 'accepted' ? 'selected' : '' }}>{{ __('admin.accepted') }}</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>{{ __('admin.in_progress') }}</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('admin.completed') }}</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ __('admin.cancelled') }}</option>
                    <option value="reviewed" {{ request('status') == 'reviewed' ? 'selected' : '' }}>{{ __('admin.reviewed') }}</option>
                </select>
                <select name="area_id" class="w-full rounded-md border-gray-300">
                    <option value="">{{ __('admin.all_areas') }}</option>
                    @foreach($areas as $area)
                        <option value="{{ $area->id }}" {{ request('area_id') == $area->id ? 'selected' : '' }}>{{ $area->name }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="w-full rounded-md border-gray-300">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="w-full rounded-md border-gray-300">
                <div class="xl:col-span-6 flex flex-col sm:flex-row gap-2 sm:justify-end">
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ __('admin.apply_filters') }}</button>
                    <a href="{{ route('admin.visits.index') }}" class="w-full sm:w-auto px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-center">{{ __('admin.clear') }}</a>
                </div>
            </form>
        </div>

        <!-- Visits Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.client') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.technician') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supervisor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.scheduled_date') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.status') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.areas_regions') }}</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($visits as $visit)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $visit->subscription->client->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $visit->technician->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $visit->supervisor->name ?? 'Unassigned' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $visit->scheduled_date ? \Carbon\Carbon::parse($visit->scheduled_date)->format('M d, Y') : 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $visit->status == 'completed' ? 'bg-green-100 text-green-800' : 
                                       ($visit->status == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-blue-100 text-blue-800') }}">
                                    {{ __('admin.' . str_replace(' ', '_', $visit->status ?? 'pending')) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $visit->area->name ?? 'N/A' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="{{ route('admin.visits.show', $visit) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">{{ __('admin.no_visits_found') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $visits->links() }}
        </div>
    </div>
</x-admin-layout>

