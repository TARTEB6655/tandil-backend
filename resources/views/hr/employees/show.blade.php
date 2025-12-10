<x-hr-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Employee Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">View complete employee information and manage user account.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('hr.employees.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                    ← Back to Employees
                </a>
                <a href="{{ route('hr.employees.edit', $employee->id) }}" 
                   class="px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Edit
                </a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Employee Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Employee Information</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Employee ID</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->employee_id }}</p>
                    </div>
                    @if($employee->designation)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Designation</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->designation }}</p>
                    </div>
                    @endif
                    @if($employee->region)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Region</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->region }}</p>
                    </div>
                    @endif
                    @if($employee->phone)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Phone</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->phone }}</p>
                    </div>
                    @endif
                    @if($employee->joining_date)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Joining Date</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">
                            {{ \Carbon\Carbon::parse($employee->joining_date)->format('M d, Y') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- User Information -->
            @if($employee->user)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <div class="flex items-center justify-between mb-3 sm:mb-4">
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">User Account</h2>
                    <!-- Status Update Form -->
                    <form action="{{ route('hr.employees.update-user-status', $employee->id) }}" method="POST" class="inline">
                        @csrf
                        <select name="status" 
                                onchange="this.form.submit()"
                                class="text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active" {{ $employee->user->status === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ $employee->user->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </form>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Name</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->user->name }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Email</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->user->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Role</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                            {{ ucfirst(str_replace('_', ' ', $employee->user->role)) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($employee->user->status === 'active') bg-green-100 text-green-800
                            @else bg-red-100 text-red-800
                            @endif">
                            {{ ucfirst($employee->user->status ?? 'active') }}
                        </span>
                    </div>
                </div>
            </div>
            @else
            <div class="bg-yellow-50 rounded-xl border border-yellow-200 shadow-sm p-4 sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 flex-1">
                        <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="text-xs sm:text-sm font-medium text-yellow-800">No User Account Linked</p>
                            <p class="text-xs text-yellow-700 mt-1">This employee record is not linked to a user account. Create one to enable system access.</p>
                        </div>
                    </div>
                    <button 
                        onclick="document.getElementById('createUserModal').classList.remove('hidden')"
                        class="px-3 sm:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 whitespace-nowrap">
                        Create User Account
                    </button>
                </div>
            </div>
            @endif

            <!-- Performance Statistics -->
            @if($performanceStats ?? null)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Performance Statistics</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                    @if($performanceStats['type'] === 'technician')
                        <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-1">Total Visits</p>
                            <p class="text-xl sm:text-2xl font-semibold text-blue-700">{{ number_format($performanceStats['total_visits'] ?? 0) }}</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-green-600 uppercase tracking-wide mb-1">Completed</p>
                            <p class="text-xl sm:text-2xl font-semibold text-green-700">{{ number_format($performanceStats['completed_visits'] ?? 0) }}</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-purple-600 uppercase tracking-wide mb-1">Reports</p>
                            <p class="text-xl sm:text-2xl font-semibold text-purple-700">{{ number_format($performanceStats['total_reports'] ?? 0) }}</p>
                        </div>
                    @elseif($performanceStats['type'] === 'supervisor')
                        <div class="bg-blue-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-1">Total Visits</p>
                            <p class="text-xl sm:text-2xl font-semibold text-blue-700">{{ number_format($performanceStats['total_visits'] ?? 0) }}</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-purple-600 uppercase tracking-wide mb-1">Total Reports</p>
                            <p class="text-xl sm:text-2xl font-semibold text-purple-700">{{ number_format($performanceStats['total_reports'] ?? 0) }}</p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-green-600 uppercase tracking-wide mb-1">Approved</p>
                            <p class="text-xl sm:text-2xl font-semibold text-green-700">{{ number_format($performanceStats['approved_reports'] ?? 0) }}</p>
                        </div>
                        <div class="bg-indigo-50 rounded-lg p-3 sm:p-4">
                            <p class="text-xs font-medium text-indigo-600 uppercase tracking-wide mb-1">Areas</p>
                            <p class="text-xl sm:text-2xl font-semibold text-indigo-700">{{ number_format($performanceStats['supervised_areas'] ?? 0) }}</p>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Quick Actions -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="{{ route('hr.employees.edit', $employee->id) }}" 
                       class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Edit Employee
                    </a>
                    <form action="{{ route('hr.employees.destroy', $employee->id) }}" method="POST" 
                          onsubmit="return confirm('Are you sure you want to delete this employee record?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" 
                                class="block w-full text-center px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100">
                            Delete Employee
                        </button>
                    </form>
                </div>
            </div>

            <!-- Metadata -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Record Information</h3>
                <div class="space-y-2">
                    <div>
                        <p class="text-xs text-gray-500">Created</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @if($employee->updated_at && $employee->updated_at != $employee->created_at)
                    <div>
                        <p class="text-xs text-gray-500">Last Updated</p>
                        <p class="text-xs sm:text-sm font-medium text-gray-900">{{ $employee->updated_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Account Modal -->
    @if(!$employee->user)
    <div id="createUserModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-gray-900">Create User Account</h3>
                    <button 
                        onclick="document.getElementById('createUserModal').classList.add('hidden')"
                        class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if($errors->any())
                    <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded-md">
                        <ul class="list-disc list-inside text-xs sm:text-sm text-red-700">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('hr.employees.create-user', $employee->id) }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="name" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Name *</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="email" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Email *</label>
                        <input type="email" 
                               name="email" 
                               id="email" 
                               value="{{ old('email') }}" 
                               required
                               class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="phone" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Phone</label>
                        <input type="text" 
                               name="phone" 
                               id="phone" 
                               value="{{ old('phone', $employee->phone) }}" 
                               class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="role" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Role *</label>
                        <select name="role" 
                                id="role" 
                                required
                                class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Role</option>
                            <option value="technician" {{ old('role') === 'technician' ? 'selected' : '' }}>Technician</option>
                            <option value="supervisor" {{ old('role') === 'supervisor' ? 'selected' : '' }}>Supervisor</option>
                            <option value="area_manager" {{ old('role') === 'area_manager' ? 'selected' : '' }}>Area Manager</option>
                        </select>
                    </div>

                    <div>
                        <label for="password" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Password *</label>
                        <input type="password" 
                               name="password" 
                               id="password" 
                               required
                               class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                        <input type="password" 
                               name="password_confirmation" 
                               id="password_confirmation" 
                               required
                               class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label for="status" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select name="status" 
                                id="status" 
                                required
                                class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button 
                            type="button"
                            onclick="document.getElementById('createUserModal').classList.add('hidden')"
                            class="flex-1 px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button 
                            type="submit"
                            class="flex-1 px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    @if(session('success'))
        <script>
            setTimeout(function() {
                document.querySelector('[x-data]') && Alpine.store && Alpine.store('notify') && Alpine.store('notify').show('{{ session('success') }}', 'success');
            }, 100);
        </script>
    @endif

    @if(session('error'))
        <script>
            setTimeout(function() {
                document.querySelector('[x-data]') && Alpine.store && Alpine.store('notify') && Alpine.store('notify').show('{{ session('error') }}', 'error');
            }, 100);
        </script>
    @endif
</x-hr-layout>

