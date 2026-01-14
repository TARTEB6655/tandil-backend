<x-hr-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Add Employee</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Create a new employee record.</p>
            </div>
            <a href="{{ route('hr.employees.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Employees
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <ul class="list-disc list-inside text-xs sm:text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
        <form action="{{ route('hr.employees.store') }}" method="POST" class="space-y-4 sm:space-y-6">
            @csrf

            <!-- User Selection -->
            <div>
                <label for="user_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">User (Optional)</label>
                <select name="user_id" id="user_id"
                        class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">Select a user (optional) - Or create user account later</option>
                    @foreach($availableUsers as $user)
                        <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }} ({{ $user->email }}) - {{ ucfirst($user->role) }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Select an existing user to link this employee record to, or leave empty and create a user account later from the employee details page.</p>
            </div>

            <!-- Employee ID -->
            <div>
                <label for="employee_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Employee ID *</label>
                <input type="text" 
                       name="employee_id" 
                       id="employee_id" 
                       value="{{ old('employee_id') }}" 
                       required
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., EMP001">
                <p class="mt-1 text-xs text-gray-500">Unique employee identifier.</p>
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Phone</label>
                <input type="text" 
                       name="phone" 
                       id="phone" 
                       value="{{ old('phone') }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="Phone number">
            </div>

            <!-- Designation -->
            <div>
                <label for="designation" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Designation</label>
                <input type="text" 
                       name="designation" 
                       id="designation" 
                       value="{{ old('designation') }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., Senior Technician">
            </div>

            <!-- Region -->
            <div>
                <label for="region" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Region</label>
                <input type="text" 
                       name="region" 
                       id="region" 
                       value="{{ old('region') }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., Dubai, Abu Dhabi">
            </div>

            <!-- Joining Date -->
            <div>
                <label for="joining_date" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Joining Date</label>
                <input type="date" 
                       name="joining_date" 
                       id="joining_date" 
                       value="{{ old('joining_date') }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-4">
                <a href="{{ route('hr.employees.index') }}" 
                   class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                    Cancel
                </a>
                <button type="submit" 
                        class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Create Employee
                </button>
            </div>
        </form>
    </div>
</x-hr-layout>

