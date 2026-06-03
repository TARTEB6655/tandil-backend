<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit banner</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update image and settings for the customer home screen.</p>
            </div>
            <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Banners
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('admin.banners.update', $banner->id) }}" enctype="multipart/form-data" class="p-6 space-y-8">
                @csrf
                @method('PUT')

                <!-- Current image -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Current image</h2>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-50 dark:bg-gray-700/50 inline-block">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title ?: 'Banner' }}" class="max-w-md h-40 object-cover">
                    </div>
                </div>

                <!-- Replace image -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Replace image (optional)</h2>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New image</label>
                        <input type="file" name="image" id="image" accept="image/*"
                               class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50"
                               onchange="previewImage(this)">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Leave empty to keep current. Recommended 1200×400px. Max 5MB.</p>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div id="image-preview" class="mt-4 hidden">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">New image preview</p>
                            <img id="preview-img" src="" alt="Preview" class="max-w-md h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-600 shadow-inner">
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Content</h2>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title (optional)</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $banner->title) }}" placeholder="e.g. Summer Sale"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description / subtitle (optional)</label>
                        <textarea name="description" id="description" rows="2" placeholder="e.g. Learn More"
                                  class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $banner->description) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Shown below title on app slider.</p>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="button_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button text (optional)</label>
                        <input type="text" name="button_text" id="button_text" value="{{ old('button_text', $banner->button_text) }}" placeholder="e.g. Learn More"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-xs">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">CTA label (e.g. Learn More, View).</p>
                        @error('button_text')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Button link -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Button link</h2>
                    <div>
                        <label for="button_link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Button link (optional)</label>
                        <input type="text" name="button_link" id="button_link" value="{{ old('button_link', $banner->action_type === 'route' ? $banner->action_value : ($banner->action_value ?? $banner->link)) }}" placeholder="client.shop.index or https://example.com"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Internal route (e.g. <code class="text-indigo-600">client.shop.index</code>) or full URL (<code>https://...</code>). Shortcuts: <code>shop</code>, <code>cart</code>. Leave empty for no link.</p>
                        @if($banner->resolved_href)
                            <p class="mt-1 text-xs text-green-600 dark:text-green-400">Resolves to: {{ $banner->resolved_href }}</p>
                        @endif
                        @error('button_link')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Display -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Display</h2>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Order (priority)</label>
                        <input type="number" name="priority" id="priority" value="{{ old('priority', $banner->priority) }}" min="0"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-xs">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower number = shown first. You can also reorder on the banners list.</p>
                        @error('priority')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $banner->is_active) ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (visible on customer app)</label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        Save changes
                    </button>
                    <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</x-admin-layout>
