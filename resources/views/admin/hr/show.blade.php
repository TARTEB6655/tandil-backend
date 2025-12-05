<x-admin-layout>
    <div class="space-y-6">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Employee Details
        </h2>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Employee Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Employee ID</p>
                        <p class="text-sm font-medium text-gray-900">{{ $employee->employee_id }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="text-sm font-medium text-gray-900">{{ $employee->user->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Email</p>
                        <p class="text-sm font-medium text-gray-900">{{ $employee->user->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Role</p>
                        <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $employee->user->role ?? 'N/A')) }}</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.hr.edit', $employee->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="{{ route('admin.hr.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Employees</a>
            </div>
        </div>
    </div>
</x-admin-layout>


