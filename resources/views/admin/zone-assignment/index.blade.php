<x-admin-layout>
    <div class="space-y-6" x-data="{ tab: 'zones' }">
        <div class="flex flex-wrap justify-between items-center gap-4 mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                {{ __('admin.zone_assignment') }}
            </h1>
            <a href="{{ route('admin.areas.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('admin.new_zone') }}
            </a>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="flex gap-1" aria-label="Tabs">
                <button type="button" @click="tab = 'zones'"
                    :class="tab === 'zones' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                    {{ __('admin.zones') }}
                </button>
                <button type="button" @click="tab = 'supervisors'"
                    :class="tab === 'supervisors' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                    {{ __('admin.supervisors') }}
                </button>
                <button type="button" @click="tab = 'technicians'"
                    :class="tab === 'technicians' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="px-4 py-3 text-sm font-medium border-b-2 transition-colors">
                    {{ __('admin.technicians') }}
                </button>
            </nav>
        </div>

        <!-- Zones tab -->
        <div x-show="tab === 'zones'" x-cloak class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.zone') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.country') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.supervisors') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.technicians') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($areas as $area)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $area->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $area->country ?? 'UAE' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if($area->supervisors->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @else
                                        {{ $area->supervisors->pluck('name')->join(', ') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if($area->technicians->isEmpty())
                                        <span class="text-gray-400">—</span>
                                    @else
                                        {{ $area->technicians->pluck('name')->join(', ') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <a href="{{ route('admin.areas.edit', $area->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">{{ __('admin.edit') }}</a>
                                    <span class="mx-1 text-gray-300">|</span>
                                    <a href="{{ route('admin.areas.show', $area->id) }}" class="text-gray-600 dark:text-gray-400 hover:underline">{{ __('admin.view') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('admin.no_zones_yet') }} <a href="{{ route('admin.areas.create') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">{{ __('admin.create_one') }}</a>.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Supervisors tab -->
        <div x-show="tab === 'supervisors'" x-cloak class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.email') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.assigned_zones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($supervisors as $sup)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $sup->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $sup->email }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if($sup->supervisedAreas->isEmpty())
                                        <span class="text-amber-600 dark:text-amber-400">{{ __('admin.none_assigned') }}</span>
                                    @else
                                        {{ $sup->supervisedAreas->pluck('name')->join(', ') }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('admin.no_supervisors_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Technicians tab -->
        <div x-show="tab === 'technicians'" x-cloak class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.employee_id') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.assigned_zones') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('admin.specializations') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($technicians as $tech)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tech->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $tech->employee->employee_id ?? '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @if($tech->assignedAreas->isEmpty())
                                        <span class="text-amber-600 dark:text-amber-400">{{ __('admin.none_assigned') }}</span>
                                    @else
                                        {{ $tech->assignedAreas->pluck('name')->join(', ') }}
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    @php
                                        $specs = $tech->employee->specializations ?? [];
                                    @endphp
                                    @if(empty($specs))
                                        <span class="text-gray-400">—</span>
                                    @else
                                        {{ is_array($specs) ? implode(', ', $specs) : $specs }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">{{ __('admin.no_technicians_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
