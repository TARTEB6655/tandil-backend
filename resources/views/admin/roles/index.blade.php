<x-admin-layout>
    <div class="space-y-4 sm:space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">{{ __('admin.roles_management') }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ __('admin.roles_manage_description') }}</p>
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
            <a href="{{ route('admin.roles.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors duration-200 shadow-sm hover:shadow-md">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('admin.create_new_role') }}
            </a>
        </div>

        <!-- Roles Table Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.role_name') }}</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden lg:table-cell">{{ __('admin.description') }}</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.users_count') }}</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider hidden md:table-cell">{{ __('admin.permissions') }}</th>
                            <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($roles as $role)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 rounded-full bg-gray-700 flex items-center justify-center text-white flex-shrink-0">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                        </div>
                                        <div class="ml-4 min-w-0 flex-1">
                                            <div class="text-sm font-semibold text-gray-900 truncate">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</div>
                                            <div class="text-xs text-gray-500 mt-0.5 truncate hidden sm:block">{{ $role->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 hidden lg:table-cell">
                                    <div class="max-w-xs">
                                        @if($role->description)
                                            <p class="text-sm text-gray-600 line-clamp-2">{{ $role->description }}</p>
                                        @else
                                            <p class="text-sm text-gray-400 italic">{{ __('admin.no_description') }}</p>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-700">
                                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        {{ $role->users_count ?? 0 }} {{ Str::plural('user', $role->users_count ?? 0) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell">
                                    <div class="flex flex-wrap gap-1.5">
                                        @if($role->permissions && $role->permissions->count() > 0)
                                            @foreach($role->permissions->take(3) as $permission)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                    {{ Str::limit($permission->name, 20) }}
                                                </span>
                                            @endforeach
                                            @if($role->permissions->count() > 3)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                                                    +{{ $role->permissions->count() - 3 }} more
                                                </span>
                                            @endif
                                        @else
                                            <span class="text-xs text-gray-400 italic">No permissions</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('admin.roles.show', $role->id) }}" 
                                           class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                           title="View Details">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('admin.roles.edit', $role->id) }}" 
                                           class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                           title="Edit Role">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <form method="POST" 
                                              action="{{ route('admin.roles.destroy', $role->id) }}" 
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this role? This action cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="text-gray-600 hover:text-gray-900 transition-colors duration-150"
                                                    title="Delete Role">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                        </svg>
                                        <p class="text-sm font-medium text-gray-900">{{ __('admin.no_roles_found') }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ __('admin.create_roles_description') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-admin-layout>
