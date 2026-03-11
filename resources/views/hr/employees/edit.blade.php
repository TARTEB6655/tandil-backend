<x-hr-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Edit Employee</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Update employee record information.</p>
            </div>
            <a href="{{ route('hr.employees.show', $employee->id) }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Employee
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
        <form action="{{ route('hr.employees.update', $employee->id) }}" method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-6">
            @csrf
            @method('PUT')

            @if($employee->user)
            <!-- Profile Picture (form-data: file upload) -->
            <div>
                <label for="profile_picture" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                @if($employee->user->profile_picture_url ?? null)
                    <div class="mb-2">
                        <img src="{{ $employee->user->profile_picture_url }}" alt="Current" class="w-16 h-16 rounded-full object-cover border border-gray-200">
                    </div>
                @endif
                <input type="file"
                       name="profile_picture"
                       id="profile_picture"
                       accept="image/jpeg,image/png,image/gif,image/webp"
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-gray-500">Optional. JPG, PNG, GIF or WebP. Leave empty to keep current.</p>
            </div>
            @endif

            <!-- Employee ID -->
            <div>
                <label for="employee_id" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Employee ID *</label>
                <input type="text" 
                       name="employee_id" 
                       id="employee_id" 
                       value="{{ old('employee_id', $employee->employee_id) }}" 
                       required
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., EMP001">
            </div>

            <!-- Phone -->
            <div>
                <label for="phone" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Phone</label>
                <input type="text" 
                       name="phone" 
                       id="phone" 
                       value="{{ old('phone', $employee->phone) }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="Phone number">
            </div>

            <!-- Designation -->
            <div>
                <label for="designation" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Designation</label>
                <input type="text" 
                       name="designation" 
                       id="designation" 
                       value="{{ old('designation', $employee->designation) }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., Senior Technician">
            </div>

            <!-- Region -->
            <div>
                <label for="region" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Region</label>
                <input type="text" 
                       name="region" 
                       id="region" 
                       value="{{ old('region', $employee->region) }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                       placeholder="e.g., Dubai, Abu Dhabi">
            </div>

            <!-- Joining Date -->
            <div>
                <label for="joining_date" class="block text-xs sm:text-sm font-medium text-gray-700 mb-2">Joining Date</label>
                <input type="date" 
                       name="joining_date" 
                       id="joining_date" 
                       value="{{ old('joining_date', $employee->joining_date ? \Carbon\Carbon::parse($employee->joining_date)->format('Y-m-d') : '') }}" 
                       class="w-full text-xs sm:text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <!-- User Info (Read-only) -->
            @if($employee->user)
            <div class="bg-gray-50 rounded-lg p-3 sm:p-4">
                <p class="text-xs sm:text-sm font-medium text-gray-700 mb-2">Linked User Account</p>
                <p class="text-xs sm:text-sm text-gray-900">{{ $employee->user->name }} ({{ $employee->user->email }})</p>
                <p class="text-xs text-gray-500 mt-1">User account cannot be changed after creation.</p>
            </div>
            @endif

            <!-- Submit Buttons -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-4">
                <a href="{{ route('hr.employees.show', $employee->id) }}" 
                   class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-center">
                    Cancel
                </a>
                <button type="submit" 
                        class="w-full sm:w-auto px-4 py-2 text-xs sm:text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                    Update Employee
                </button>
            </div>
        </form>
    </div>
</x-hr-layout>

