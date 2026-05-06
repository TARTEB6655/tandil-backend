<x-admin-layout>
    <div class="space-y-4 sm:space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">{{ __('admin.users_management') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('admin.manage_monitor_users') }}</p>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Action Button -->
        <div class="flex justify-end mb-4">
            <a href="{{ route('admin.users.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors duration-200 shadow-sm hover:shadow-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('admin.create_new_user') }}
            </a>
        </div>

        <!-- Filters Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 md:p-6 mb-6">
            <form method="GET" action="{{ route('admin.users.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">Search</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" 
                                   name="search" 
                                   value="{{ request('search') }}" 
                                   placeholder="{{ __('admin.search_by_name_email_phone') }}" 
                                   class="block w-full pl-10 pr-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition">
                        </div>
                    </div>

                    <!-- Role Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.role') }}</label>
                        <select name="role" class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition">
                            <option value="">{{ __('admin.all_roles') }}</option>
                            <option value="admin" {{ request('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="client" {{ request('role') == 'client' ? 'selected' : '' }}>Client</option>
                            <option value="technician" {{ request('role') == 'technician' ? 'selected' : '' }}>Technician</option>
                            <option value="supervisor" {{ request('role') == 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                            <option value="area_manager" {{ request('role') == 'area_manager' ? 'selected' : '' }}>Area Manager</option>
                            <option value="hr" {{ request('role') == 'hr' ? 'selected' : '' }}>HR</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide mb-1.5">{{ __('admin.status') }}</label>
                        <select name="status" class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition">
                            <option value="">{{ __('admin.all_statuses') }}</option>
                            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('admin.active') }}</option>
                            <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('admin.inactive') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors duration-200 shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Apply Filters
                    </button>
                    <a href="{{ route('admin.users.index') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        {{ __('admin.clear') }}
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.user') }}</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden sm:table-cell">{{ __('admin.email') }}</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden md:table-cell">{{ __('admin.phone') }}</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.role') }}</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden lg:table-cell">Active Zone(s)</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.status') }}</th>
                            <th class="px-4 sm:px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $user)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="ml-4 min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 truncate">{{ $user->name }}</div>
                                            <div class="text-xs text-gray-500 sm:hidden mt-0.5 truncate">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600 hidden sm:table-cell">{{ $user->email }}</td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600 hidden md:table-cell">{{ $user->phone ?? 'N/A' }}</td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                        {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                    @php
                                        $role = strtolower((string) $user->role);
                                        $zones = collect();
                                        if ($role === 'supervisor') {
                                            $zones = $user->supervisedAreas ?? collect();
                                        } elseif ($role === 'technician') {
                                            $zones = $user->assignedAreas ?? collect();
                                        }
                                    @endphp
                                    @if($zones->isNotEmpty())
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($zones->take(3) as $zone)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-xs font-medium">
                                                    {{ $zone->name }}
                                                </span>
                                            @endforeach
                                            @if($zones->count() > 3)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-medium">
                                                    +{{ $zones->count() - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">Not assigned</span>
                                    @endif
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $user->status == 'active' ? 'bg-gray-100 text-gray-700' : 'bg-gray-200 text-gray-600' }}">
                                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $user->status == 'active' ? 'bg-gray-600' : 'bg-gray-400' }}"></span>
                                        {{ ucfirst($user->status) }}
                                    </span>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('admin.users.show', $user) }}" 
                                           class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                           title="{{ __('admin.view') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.users.edit', $user) }}" 
                                           class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                           title="{{ __('admin.edit') }}">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 sm:px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <p class="text-sm font-medium text-gray-900">{{ __('admin.no_users_found') }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('admin.try_adjusting_filters') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="flex justify-center">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    {{ $users->links() }}
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>
