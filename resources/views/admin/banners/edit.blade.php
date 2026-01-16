<x-admin-layout>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Edit Banner</h1>
                <p class="mt-1 text-sm text-gray-500">Update banner details</p>
            </div>
            <a href="{{ route('admin.banners.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
                ← Back to Banners
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <form method="POST" action="{{ route('admin.banners.update', $banner->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <!-- Current Image Preview -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Image</label>
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" class="max-w-md h-48 object-cover rounded-lg border border-gray-200">
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Title (Optional)</label>
                        <input type="text" name="title" id="title" value="{{ old('title', $banner->title) }}" 
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('title')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Image -->
                    <div>
                        <label for="image" class="block text-sm font-medium text-gray-700 mb-2">
                            New Banner Image (Leave empty to keep current)
                        </label>
                        <input type="file" name="image" id="image" accept="image/*"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               onchange="previewImage(this)">
                        <p class="mt-1 text-xs text-gray-500">Recommended: 1200x400px or similar aspect ratio. Max 5MB</p>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <div id="image-preview" class="mt-3 hidden">
                            <img id="preview-img" src="" alt="Preview" class="max-w-md h-48 object-cover rounded-lg border border-gray-200">
                        </div>
                    </div>

                    <!-- Action Type -->
                    <div>
                        <label for="action_type" class="block text-sm font-medium text-gray-700 mb-2">Action Type</label>
                        <select name="action_type" id="action_type" 
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                onchange="toggleActionValue()">
                            <option value="link" {{ old('action_type', $banner->action_type ?: 'link') == 'link' ? 'selected' : '' }}>External Link (URL)</option>
                            <option value="route" {{ old('action_type', $banner->action_type) == 'route' ? 'selected' : '' }}>Internal Route</option>
                            <option value="none" {{ old('action_type', $banner->action_type) == 'none' ? 'selected' : '' }}>No Action</option>
                        </select>
                    </div>

                    <!-- Action Value / Link -->
                    <div id="action-value-group">
                        <label for="action_value" id="action-label" class="block text-sm font-medium text-gray-700 mb-2">Link URL</label>
                        <input type="text" name="action_value" id="action_value" value="{{ old('action_value', $banner->action_value) }}" 
                               placeholder="https://example.com or route name"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500" id="action-hint">Enter a full URL (e.g., https://example.com)</p>
                        @error('action_value')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Legacy Link Field -->
                    <div>
                        <label for="link" class="block text-sm font-medium text-gray-700 mb-2">Link (Legacy - Optional)</label>
                        <input type="url" name="link" id="link" value="{{ old('link', $banner->link) }}" 
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">This will be used if action_value is empty</p>
                    </div>

                    <!-- Priority -->
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                        <input type="number" name="priority" id="priority" value="{{ old('priority', $banner->priority) }}" min="0"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Lower numbers appear first</p>
                        @error('priority')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $banner->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="text-sm font-medium text-gray-700">Active (visible on customer app)</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex gap-4">
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition-colors">
                        Update Banner
                    </button>
                    <a href="{{ route('admin.banners.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function toggleActionValue() {
            const actionType = document.getElementById('action_type').value;
            const actionValueGroup = document.getElementById('action-value-group');
            const actionLabel = document.getElementById('action-label');
            const actionValue = document.getElementById('action_value');
            const actionHint = document.getElementById('action-hint');

            if (actionType === 'none') {
                actionValueGroup.style.display = 'none';
            } else {
                actionValueGroup.style.display = 'block';
                if (actionType === 'link') {
                    actionLabel.textContent = 'Link URL';
                    actionValue.placeholder = 'https://example.com';
                    actionHint.textContent = 'Enter a full URL (e.g., https://example.com)';
                } else if (actionType === 'route') {
                    actionLabel.textContent = 'Route Name';
                    actionValue.placeholder = 'client.dashboard or /client/products';
                    actionHint.textContent = 'Enter a route name (e.g., client.dashboard) or path';
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleActionValue();
        });
    </script>
</x-admin-layout>
