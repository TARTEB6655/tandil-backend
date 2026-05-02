<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-medium text-gray-900">Send Notification</h1>
                    <p class="mt-1 text-sm md:text-base text-gray-600">Send push notifications and announcements to users</p>
                </div>
                <a href="{{ route('admin.notifications.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    ← Back to Notifications
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg flex items-center gap-2">
                <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Send Notification Form -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.notifications.send') }}" class="space-y-6">
                @csrf

                <!-- Notification Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Send To</label>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="all" class="text-indigo-600 focus:ring-indigo-500" checked>
                            <div>
                                <p class="text-sm font-medium text-gray-900">All Users</p>
                                <p class="text-xs text-gray-500">Send to all registered users</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="role" class="text-indigo-600 focus:ring-indigo-500">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Specific Role</p>
                                <p class="text-xs text-gray-500 mb-2">Send to users with a specific role</p>
                                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" disabled id="role-select">
                                    <option value="">Select Role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="users" class="text-indigo-600 focus:ring-indigo-500">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Specific Users</p>
                                <p class="text-xs text-gray-500 mb-2">Send to selected users</p>
                                <select name="user_ids[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" disabled id="users-select">
                                    @foreach(\App\Models\User::all() as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Notification Title</label>
                    <input type="text" 
                           id="title" 
                           name="title" 
                           value="{{ old('title') }}"
                           placeholder="Enter notification title"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           required>
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Message -->
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700 mb-2">Message</label>
                    <textarea id="message" 
                              name="message" 
                              rows="5"
                              placeholder="Enter notification message"
                              class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                              required>{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                @php
                    $audienceRoles = [
                        'client' => 'Customers (client role)',
                        'technician' => 'Technicians',
                        'supervisor' => 'Supervisors',
                        'area_manager' => 'Area managers',
                        'hr' => 'HR',
                        'admin' => 'Admins',
                    ];
                @endphp
                <details class="border border-gray-200 rounded-xl bg-gray-50/80 p-4">
                    <summary class="text-sm font-medium text-gray-800 cursor-pointer">Optional: different title & message per role</summary>
                    <p class="text-xs text-gray-600 mt-2 mb-4">Leave blank to use the main title and message above for everyone. If you fill a role’s title or message, that role gets your custom text (others keep the default).</p>
                    <div class="space-y-6">
                        @foreach($audienceRoles as $roleKey => $label)
                            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                                <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ $label }}</h3>
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Title override</label>
                                        <input type="text" name="messages_by_role[{{ $roleKey }}][title]"
                                               value="{{ old('messages_by_role.'.$roleKey.'.title') }}"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg"
                                               placeholder="Optional">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Message override</label>
                                        <textarea name="messages_by_role[{{ $roleKey }}][message]" rows="3"
                                                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg"
                                                  placeholder="Optional">{{ old('messages_by_role.'.$roleKey.'.message') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </details>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.notifications.index') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-medium">
                        Send Notification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const typeInputs = document.querySelectorAll('input[name="type"]');
            const roleSelect = document.getElementById('role-select');
            const usersSelect = document.getElementById('users-select');

            typeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value === 'role') {
                        roleSelect.disabled = false;
                        roleSelect.required = true;
                        usersSelect.disabled = true;
                        usersSelect.required = false;
                    } else if (this.value === 'users') {
                        usersSelect.disabled = false;
                        usersSelect.required = true;
                        roleSelect.disabled = true;
                        roleSelect.required = false;
                    } else {
                        roleSelect.disabled = true;
                        roleSelect.required = false;
                        usersSelect.disabled = true;
                        usersSelect.required = false;
                    }
                });
            });
        });
    </script>
</x-admin-layout>

