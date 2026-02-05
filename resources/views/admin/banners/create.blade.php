<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Add home screen banner</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">This banner will appear on the customer app home screen.</p>
            </div>
            <a href="{{ route('admin.banners.index') }}" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Banners
            </a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="p-6 space-y-8">
                @csrf

                <!-- Image -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Image</h2>
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Banner image <span class="text-red-500">*</span>
                        </label>
                        <input type="file" name="image" id="image" accept="image/*" required
                               class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-900/30 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50"
                               onchange="previewImage(this)">
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Recommended 1200×400px or similar. Max 5MB. JPG, PNG, GIF, SVG, WebP.</p>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                        <div id="image-preview" class="mt-4 hidden">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Preview</p>
                            <img id="preview-img" src="" alt="Preview" class="max-w-md h-40 object-cover rounded-lg border border-gray-200 dark:border-gray-600 shadow-inner">
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Content</h2>
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title (optional)</label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" placeholder="e.g. Summer Sale"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Action / Link -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Action when tapped</h2>
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                        <select name="action_type" id="action_type"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                onchange="toggleActionValue()">
                            <option value="link" {{ old('action_type', 'link') == 'link' ? 'selected' : '' }}>External link (URL)</option>
                            <option value="route" {{ old('action_type') == 'route' ? 'selected' : '' }}>Internal route</option>
                            <option value="none" {{ old('action_type') == 'none' ? 'selected' : '' }}>No action</option>
                        </select>
                    </div>
                    <div id="action-value-group">
                        <label for="action_value" id="action-label" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL or route</label>
                        <input type="text" name="action_value" id="action_value" value="{{ old('action_value') }}"
                               placeholder="https://example.com or route name"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="action-hint">Enter a full URL (e.g. https://example.com)</p>
                        @error('action_value')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="link" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Legacy link (optional)</label>
                        <input type="url" name="link" id="link" value="{{ old('link') }}" placeholder="https://..."
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Used if action value is empty</p>
                    </div>
                </div>

                <!-- Display -->
                <div class="space-y-4">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider border-b border-gray-200 dark:border-gray-700 pb-2">Display</h2>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Order (priority)</label>
                        <input type="number" name="priority" id="priority" value="{{ old('priority', 0) }}" min="0"
                               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-xs">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower number = shown first. Default 0.</p>
                        @error('priority')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700">
                        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (visible on customer app)</label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        Create banner
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
        function toggleActionValue() {
            var actionType = document.getElementById('action_type').value;
            var group = document.getElementById('action-value-group');
            var label = document.getElementById('action-label');
            var input = document.getElementById('action_value');
            var hint = document.getElementById('action-hint');
            if (actionType === 'none') {
                group.style.display = 'none';
            } else {
                group.style.display = 'block';
                if (actionType === 'link') {
                    label.textContent = 'URL or route';
                    input.placeholder = 'https://example.com';
                    hint.textContent = 'Enter a full URL (e.g. https://example.com)';
                } else {
                    label.textContent = 'Route name';
                    input.placeholder = 'e.g. client.dashboard or /client/products';
                    hint.textContent = 'Enter a route name or path';
                }
            }
        }
        document.addEventListener('DOMContentLoaded', toggleActionValue);
    </script>
</x-admin-layout>
