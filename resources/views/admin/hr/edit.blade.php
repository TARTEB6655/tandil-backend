<x-admin-layout>
    <div class="space-y-6">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Edit Employee
        </h2>

        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.hr.update', $employee->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Employee ID</label>
                    <input type="text" name="employee_id" value="{{ old('employee_id', $employee->employee_id) }}" class="mt-1 block w-full rounded-md border-gray-300">
                    @error('employee_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Employee</button>
                    <a href="{{ route('admin.hr.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>


