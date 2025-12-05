<x-admin-layout>
    <div class="space-y-4 sm:space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <h1 class="text-xl font-medium text-gray-900">Create New User</h1>
            <p class="mt-1 text-sm md:text-base text-gray-600">Add a new user to the system and assign their role</p>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-4">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm font-medium">Please fix the following errors:</span>
                </div>
                <ul class="list-disc list-inside text-sm space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Create User Form Card -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 md:p-6">
            <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
                @csrf

                <!-- Personal Information Section -->
                <div class="space-y-5">
                    <h3 class="text-base font-medium text-gray-900 border-b border-gray-200 pb-2">Personal Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name"
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   required 
                                   class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('name') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                   placeholder="Enter full name">
                            @error('name')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address <span class="text-red-500">*</span>
                            </label>
                            <input type="email" 
                                   id="email"
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('email') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                   placeholder="user@example.com">
                            @error('email')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                                Phone Number
                            </label>
                            <input type="text" 
                                   id="phone"
                                   name="phone" 
                                   value="{{ old('phone') }}" 
                                   class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('phone') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                   placeholder="+971 XX XXX XXXX">
                            @error('phone')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Security Section -->
                <div class="space-y-5 pt-5 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 border-b border-gray-200 pb-2">Security</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Password -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   id="password"
                                   name="password" 
                                   required 
                                   minlength="8"
                                   class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('password') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror"
                                   placeholder="Minimum 8 characters">
                            @error('password')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-gray-500">Must be at least 8 characters long</p>
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm Password <span class="text-red-500">*</span>
                            </label>
                            <input type="password" 
                                   id="password_confirmation"
                                   name="password_confirmation" 
                                   required 
                                   minlength="8"
                                   class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition"
                                   placeholder="Re-enter password">
                        </div>
                    </div>
                </div>

                <!-- Role & Status Section -->
                <div class="space-y-5 pt-5 border-t border-gray-200">
                    <h3 class="text-base font-medium text-gray-900 border-b border-gray-200 pb-2">Role & Status</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- Role Selection -->
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                                User Role <span class="text-red-500">*</span>
                            </label>
                            <select id="role"
                                    name="role" 
                                    required 
                                    class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('role') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">
                                <option value="">Select a role</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-gray-500">Select the role to assign to this user</p>
                        </div>

                        <!-- Status Selection -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Account Status <span class="text-red-500">*</span>
                            </label>
                            <select id="status"
                                    name="status" 
                                    required 
                                    class="block w-full px-3 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 focus:bg-white transition @error('status') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="suspended" {{ old('status') == 'suspended' ? 'selected' : '' }}>Suspended</option>
                            </select>
                            @error('status')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1.5 text-xs text-gray-500">Set the initial account status</p>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3 pt-5 border-t border-gray-200">
                    <a href="{{ route('admin.users.index') }}" 
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Cancel
                    </a>
                    <button type="submit" 
                            class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors duration-200 shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
