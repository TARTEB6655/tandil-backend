<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ __('admin.create_new_role') }}</h1>
                    <p class="mt-1 text-sm text-gray-500">Define a new role with permissions and assign it to users</p>
                </div>
                <a href="{{ route('admin.roles.index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Roles
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form - Left Column (2/3 width) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Create Role Form -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Role Information</h2>
                        <p class="mt-1 text-sm text-gray-500">Enter the role name and description</p>
                    </div>
                    
                    <form method="POST" action="{{ route('admin.roles.store') }}" class="p-6 space-y-6">
                @csrf

                        <!-- Role Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Role Name <span class="text-red-500">*</span>
                            </label>
                            <select id="name"
                                    name="name" 
                                    required 
                                    class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition @error('name') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">
                                <option value="">Select a role...</option>
                                @foreach($existingRoles as $existingRole)
                                    <option value="{{ $existingRole->name }}" {{ old('name') == $existingRole->name ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $existingRole->name)) }}
                                        @if($existingRole->description)
                                            - {{ Str::limit($existingRole->description, 50) }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('name')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-gray-500">Select from existing roles defined in the database</p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description
                            </label>
                            <textarea id="description"
                                      name="description" 
                                      rows="4"
                                      placeholder="Describe what this role is responsible for and what permissions it should have..."
                                      class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition resize-none @error('description') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-gray-500">Provide a clear description of this role's purpose and responsibilities</p>
                        </div>

                        <!-- Permissions Section -->
                        <div class="pt-6 border-t border-gray-200">
                <div class="mb-4">
                                <h3 class="text-base font-semibold text-gray-900 mb-1">{{ __('admin.permissions') }}</h3>
                                <p class="text-sm text-gray-500">Select the permissions this role should have</p>
                            </div>

                            @if($permissions->isEmpty())
                                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                                    <p class="text-sm text-gray-500">No permissions available. Please create permissions first.</p>
                                </div>
                            @else
                                <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
                                    @foreach($permissions as $guardName => $guardPermissions)
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <h4 class="text-sm font-semibold text-gray-700 mb-3 capitalize">
                                                {{ str_replace('_', ' ', $guardName) }} Permissions
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                @foreach($guardPermissions as $permission)
                                                    <label class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50 hover:border-gray-300 cursor-pointer transition-colors">
                                                        <input type="checkbox" 
                                                               name="permissions[]" 
                                                               value="{{ $permission->id }}"
                                                               {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}
                                                               class="mt-1 h-4 w-4 text-gray-900 focus:ring-gray-900 border-gray-300 rounded">
                                                        <div class="flex-1 min-w-0">
                                                            <span class="text-sm font-medium text-gray-900 block">
                                                                {{ ucwords(str_replace(['_', '.'], ' ', $permission->name)) }}
                                                            </span>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                <div class="mt-4 flex items-center gap-2 text-sm text-gray-600">
                                    <button type="button" 
                                            onclick="document.querySelectorAll('input[name=\"permissions[]\"]').forEach(cb => cb.checked = true)"
                                            class="text-gray-700 hover:text-gray-900 underline">
                                        Select All
                                    </button>
                                    <span>|</span>
                                    <button type="button" 
                                            onclick="document.querySelectorAll('input[name=\"permissions[]\"]').forEach(cb => cb.checked = false)"
                                            class="text-gray-700 hover:text-gray-900 underline">
                                        Deselect All
                                    </button>
                                </div>
                            @endif
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-gray-200">
                            <a href="{{ route('admin.roles.index') }}" 
                               class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-5 py-2.5 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition-colors shadow-sm hover:shadow-md">
                                {{ __('admin.create_role') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Existing Roles Sidebar - Right Column (1/3 width) -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-6">
                    <div class="px-6 py-5 border-b border-gray-200 bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-900">Existing Roles</h2>
                        <p class="mt-1 text-sm text-gray-500">{{ $existingRoles->count() }} role(s) defined</p>
                    </div>
                    
                    <div class="p-6">
                        @if($existingRoles->isEmpty())
                            <div class="text-center py-8">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <p class="text-sm text-gray-500">No roles created yet</p>
                            </div>
                        @else
                            <div class="space-y-4 max-h-[calc(100vh-300px)] overflow-y-auto pr-2">
                                @foreach($existingRoles as $role)
                                    <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-sm font-semibold text-gray-900 truncate">
                                                    {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                                </h3>
                                                <p class="text-xs text-gray-500 mt-0.5">{{ $role->name }}</p>
                                            </div>
                                            <a href="{{ route('admin.roles.edit', $role->id) }}" 
                                               class="ml-2 text-gray-400 hover:text-gray-600 transition-colors"
                                               title="Edit Role">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        </div>
                                        
                                        @if($role->description)
                                            <p class="text-xs text-gray-600 mt-2 line-clamp-2">{{ $role->description }}</p>
                                        @else
                                            <p class="text-xs text-gray-400 italic mt-2">{{ __('admin.no_description') }}</p>
                                        @endif
                                        
                                        <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
                                            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                                <span>{{ $role->users_count ?? 0 }} users</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-xs text-gray-500">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                                </svg>
                                                <span>{{ $role->permissions->count() }} permissions</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                </div>

                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <a href="{{ route('admin.roles.index') }}" 
                                   class="block w-full text-center px-4 py-2 text-sm font-medium text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                                    View All Roles
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
