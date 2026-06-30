<x-admin-layout>
    <div class="space-y-6 max-w-2xl">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Upload maintenance photo</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose the client visit this photo belongs to. It will appear on that client's app when visible is enabled.</p>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <form method="POST" action="{{ route('admin.maintenance-photos.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div>
                    <label for="visit_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Visit <span class="text-red-500">*</span></label>
                    <select name="visit_id" id="visit_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        <option value="">Select visit</option>
                        @foreach($visits as $visit)
                            <option value="{{ $visit->id }}" @selected(old('visit_id') == $visit->id)>
                                #{{ $visit->id }} — {{ $visit->subscription?->client?->name ?? 'Client' }} ({{ $visit->scheduled_date?->format('Y-m-d') }})
                            </option>
                        @endforeach
                    </select>
                    @error('visit_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="photo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Photo <span class="text-red-500">*</span></label>
                    <input type="file" name="photo" id="photo" accept="image/*" required class="block w-full text-sm">
                    @error('photo')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                    <select name="type" id="type" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700">
                        @foreach(['before', 'during', 'after'] as $type)
                            <option value="{{ $type }}" @selected(old('type', 'after') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" name="show_on_client_app" value="1" @checked(old('show_on_client_app', true)) class="rounded border-gray-300">
                    Show on client app
                </label>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium">Upload</button>
                    <a href="{{ route('admin.maintenance-photos.index') }}" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>
