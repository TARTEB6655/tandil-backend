<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-medium text-gray-900">Send Notification</h1>
                    <p class="mt-1 text-sm md:text-base text-gray-600">Send push notifications and announcements to users</p>
                </div>
                <a href="{{ route('admin.notifications.statistics') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    ← {{ __('admin.notification_statistics') }}
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
                            <input type="radio" name="type" value="all" class="text-indigo-600 focus:ring-indigo-500" @checked(old('type', 'all') === 'all')>
                            <div>
                                <p class="text-sm font-medium text-gray-900">All Users</p>
                                <p class="text-xs text-gray-500">Send to all registered users</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <input type="radio" name="type" value="role" class="text-indigo-600 focus:ring-indigo-500" @checked(old('type') === 'role')>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900">Specific Role</p>
                                <p class="text-xs text-gray-500 mb-2">Send to users with a specific role</p>
                                <select name="role" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" disabled id="role-select">
                                    <option value="">Select Role</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}" @selected(old('role') === $role->name)>{{ ucfirst($role->name) }}</option>
                                    @endforeach
                                </select>
                                <div id="role-users-panel" class="mt-3 hidden">
                                    <div id="role-users-list" class="max-h-44 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3 space-y-2 hidden"></div>
                                    <p id="role-users-empty" class="mt-2 text-xs text-gray-500 hidden">No users found for selected role.</p>
                                </div>
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

                <select name="user_ids[]" multiple class="hidden" id="users-select">
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected(collect(old('user_ids', []))->contains($user->id))>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('admin.notifications.statistics') }}" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
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
            const roleUsersPanel = document.getElementById('role-users-panel');
            const roleUsersList = document.getElementById('role-users-list');
            const roleUsersEmpty = document.getElementById('role-users-empty');
            const roleTypeInput = document.querySelector('input[name="type"][value="role"]');
            const form = document.querySelector('form[action="{{ route('admin.notifications.send') }}"]');
            const usersData = @json($usersForJs ?? []);

            function usersForRole(role) {
                if (!role) return [];
                return usersData.filter(user => user.roles.includes(role) || user.legacy_role === role);
            }

            function renderRoleUsers() {
                const selectedRole = roleSelect.value;
                const roleUsers = usersForRole(selectedRole);

                roleUsersList.innerHTML = '';
                roleUsersList.classList.add('hidden');

                if (!selectedRole || roleUsers.length === 0) {
                    roleUsersEmpty.classList.remove('hidden');
                    return;
                }

                roleUsersEmpty.classList.add('hidden');
                roleUsersList.classList.remove('hidden');
                roleUsers.forEach(user => {
                    const row = document.createElement('label');
                    row.className = 'flex items-center gap-2 text-sm text-gray-700';
                    row.innerHTML = `
                        <input type="checkbox" class="role-user-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="${user.id}">
                        <span>${user.name} (${user.email})</span>
                    `;
                    roleUsersList.appendChild(row);
                });
            }

            function setMode(type) {
                if (type === 'role') {
                    roleSelect.disabled = false;
                    roleSelect.required = true;
                    roleUsersPanel.classList.remove('hidden');
                    renderRoleUsers();
                } else {
                    roleSelect.disabled = true;
                    roleSelect.required = false;
                    roleUsersPanel.classList.add('hidden');
                }
            }

            typeInputs.forEach(input => {
                input.addEventListener('change', function() {
                    setMode(this.value);
                });
            });

            roleSelect.addEventListener('mousedown', function () {
                if (!this.disabled) return;
                if (!roleTypeInput) return;
                roleTypeInput.checked = true;
                setMode('role');
            });
            roleSelect.addEventListener('change', renderRoleUsers);

            form?.addEventListener('submit', function () {
                const selectedType = document.querySelector('input[name="type"]:checked')?.value;
                if (selectedType !== 'role') return;

                const selectedIds = Array.from(roleUsersList.querySelectorAll('.role-user-checkbox:checked'))
                    .map(cb => cb.value);

                if (selectedIds.length === 0) {
                    return; // keep type=role => send to all users in role
                }

                // Route role selection with specific users through existing users flow.
                Array.from(usersSelect.options).forEach(opt => {
                    opt.selected = selectedIds.includes(opt.value);
                });
                const allTypeInput = document.querySelector('input[name="type"][value="all"]');
                if (allTypeInput) allTypeInput.checked = false;
                if (roleTypeInput) roleTypeInput.checked = false;
                const hiddenType = document.createElement('input');
                hiddenType.type = 'hidden';
                hiddenType.name = 'type';
                hiddenType.value = 'users';
                form.appendChild(hiddenType);
                roleSelect.required = false;
            });

            const initialType = document.querySelector('input[name="type"]:checked')?.value || 'all';
            setMode(initialType);
        });
    </script>
</x-admin-layout>

