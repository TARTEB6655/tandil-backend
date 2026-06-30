<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit Maintenance Photo</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $photo->title ?: 'Untitled' }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <form method="POST" action="{{ route('admin.maintenance-photos.update', $photo->id) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title (optional)</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $photo->title) }}" placeholder="e.g. Shoe restoration" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Current before</p>
                        <img src="{{ $photo->before_image_url }}" alt="Before" class="w-full rounded-lg border border-gray-200 dark:border-gray-600">
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Current after</p>
                        <img src="{{ $photo->after_image_url }}" alt="After" class="w-full rounded-lg border border-gray-200 dark:border-gray-600">
                    </div>
                </div>

                <div>
                    <label for="before_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Replace before image (optional)</label>
                    <input type="file" name="before_image" id="before_image" accept="image/*" class="block w-full text-sm">
                </div>

                <div>
                    <label for="after_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Replace after image (optional)</label>
                    <input type="file" name="after_image" id="after_image" accept="image/*" class="block w-full text-sm">
                </div>

                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Priority (lower shows first)</label>
                    <input type="number" name="priority" id="priority" value="{{ old('priority', $photo->priority) }}" min="0" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $photo->is_active)) class="rounded border-gray-300">
                    Active
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium">Save</button>
                    <a href="{{ route('admin.maintenance-photos.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Back</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
