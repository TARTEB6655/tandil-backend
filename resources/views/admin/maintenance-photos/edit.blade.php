<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit maintenance photo</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Visit #{{ $photo->visit_id }} — {{ $photo->visit?->subscription?->client?->name ?? 'Client' }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <form method="POST" action="{{ route('admin.maintenance-photos.update', $photo->id) }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <img src="{{ asset('storage/' . $photo->photo_path) }}" alt="Current photo" class="w-full max-w-sm rounded-lg border border-gray-200 dark:border-gray-600">
                </div>

                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Replace photo (optional)</label>
                    <input type="file" name="photo" id="photo" accept="image/*" class="block w-full text-sm">
                    @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                    <select name="type" id="type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        @foreach(['before', 'during', 'after'] as $type)
                            <option value="{{ $type }}" @selected(old('type', $photo->type) === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="show_on_client_app" value="1" @checked(old('show_on_client_app', $photo->show_on_client_app)) class="rounded border-gray-300">
                    Show on client app
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium">Save changes</button>
                    <a href="{{ route('admin.maintenance-photos.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Back</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
