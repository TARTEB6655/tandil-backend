<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            Edit Role
        </h1>

        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.roles.update', $role->id) }}">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Role Name <span class="text-red-500">*</span></label>
                    <select name="name" required class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition @error('name') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">
                        <option value="">Select a role...</option>
                        @foreach($existingRoles as $existingRole)
                            <option value="{{ $existingRole->name }}" {{ old('name', $role->name) == $existingRole->name ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $existingRole->name)) }}
                                @if($existingRole->description)
                                    - {{ Str::limit($existingRole->description, 50) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-gray-500">Select from existing roles defined in the database</p>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" rows="4" class="block w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-gray-900 focus:border-gray-900 transition resize-none @error('description') border-red-300 focus:ring-red-500 focus:border-red-500 @enderror">{{ old('description', $role->description) }}</textarea>
                    @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-gray-500">Provide a clear description of this role's purpose and responsibilities</p>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Role</button>
                    <a href="{{ route('admin.roles.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>


