<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-medium text-gray-900 mb-6">
            Area Details
        </h2>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h3 class="text-base font-medium text-gray-900 mb-4">Area Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Name</p>
                        <p class="text-sm font-medium text-gray-900">{{ $area->name }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Description</p>
                        <p class="text-sm font-medium text-gray-900">{{ $area->description ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.areas.edit', $area->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="{{ route('admin.areas.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Areas</a>
            </div>
        </div>
    </div>
</x-admin-layout>


