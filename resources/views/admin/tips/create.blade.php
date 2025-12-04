<x-admin-layout>
    <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Create New Tip
        </h2>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6">
            <form method="POST" action="{{ route('admin.tips.store') }}">
                @csrf

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" required class="mt-1 block w-full rounded-md border-gray-300">
                    @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Content</label>
                    <textarea name="content" rows="5" required class="mt-1 block w-full rounded-md border-gray-300">{{ old('content') }}</textarea>
                    @error('content') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>General</option>
                            <option value="weekly" {{ old('type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                            <option value="monthly" {{ old('type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="seasonal" {{ old('type') == 'seasonal' ? 'selected' : '' }}>Seasonal</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="status" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="published" {{ old('status') == 'published' ? 'selected' : '' }}>Published</option>
                            <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Language</label>
                        <select name="language" required class="mt-1 block w-full rounded-md border-gray-300">
                            <option value="en" {{ old('language') == 'en' ? 'selected' : '' }}>English</option>
                            <option value="ar" {{ old('language') == 'ar' ? 'selected' : '' }}>Arabic</option>
                            <option value="ur" {{ old('language') == 'ur' ? 'selected' : '' }}>Urdu</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Scheduled At (Optional)</label>
                        <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="mt-1 block w-full rounded-md border-gray-300">
                    </div>
                </div>

                <div class="flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create Tip</button>
                    <a href="{{ route('admin.tips.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-admin-layout>

