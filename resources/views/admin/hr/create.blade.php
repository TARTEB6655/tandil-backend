<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            {{ __('admin.add_employee') }}
        </h1>

        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.hr.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">User</label>
                    <select name="user_id" required class="mt-1 block w-full rounded-md border-gray-300">
                        <option value="">Select User</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Employee ID (optional, will be auto-generated)</label>
                    <input type="text" name="employee_id" value="{{ old('employee_id') }}" class="mt-1 block w-full rounded-md border-gray-300">
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">{{ __('admin.create_employee') }}</button>
                    <a href="{{ route('admin.hr.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>


