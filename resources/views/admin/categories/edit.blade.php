<x-admin-layout>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Edit Category</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Update category information and image</p>
            </div>
            <a href="{{ route('admin.categories.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Categories
            </a>
        </div>

        <!-- Form -->
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <form method="POST" action="{{ route('admin.categories.update', $category->id) }}" enctype="multipart/form-data" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Image block -->
                    <div class="lg:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category Image</label>
                        <div class="relative">
                            <input type="file" 
                                   id="image" 
                                   name="image" 
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10 rounded-lg">
                            @if($category->image_url)
                                <div id="current-image-wrap" class="relative aspect-square rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-50 dark:bg-gray-700/50">
                                    <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/40 opacity-0 hover:opacity-100 transition-opacity">
                                        <span class="text-white text-sm font-medium">Change image</span>
                                    </div>
                                </div>
                                <div id="image-preview-edit" class="hidden aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 flex flex-col items-center justify-center text-center p-4">
                                    <span class="text-sm text-gray-500 dark:text-gray-400">New image chosen</span>
                                </div>
                                <img id="image-preview-img" src="" alt="" class="hidden w-full aspect-square object-cover rounded-xl border border-gray-200 dark:border-gray-600">
                                <label class="mt-2 flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="remove_image" value="1" class="rounded border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Remove image</span>
                                </label>
                            @else
                                <div id="image-preview-edit" class="aspect-square rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 flex flex-col items-center justify-center text-center p-4 hover:border-indigo-400 dark:hover:border-indigo-500 transition-colors">
                                    <svg class="w-12 h-12 text-gray-400 dark:text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6 6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">Click to upload</span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500 mt-1">JPEG, PNG, GIF or WebP (max 2MB)</span>
                                </div>
                                <img id="image-preview-img" src="" alt="" class="hidden w-full aspect-square object-cover rounded-xl border border-gray-200 dark:border-gray-600">
                            @endif
                        </div>
                        @error('image')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="lg:col-span-2 space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category Name *</label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $category->name) }}" 
                                   required
                                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="4"
                                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $category->description) }}</textarea>
                            @error('description')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="icon" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Icon</label>
                            <select id="icon" name="icon" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— None —</option>
                                <option value="water" {{ old('icon', $category->icon) === 'water' ? 'selected' : '' }}>💧 Water</option>
                                <option value="leaf" {{ old('icon', $category->icon) === 'leaf' ? 'selected' : '' }}>🌿 Leaf</option>
                                <option value="broom" {{ old('icon', $category->icon) === 'broom' ? 'selected' : '' }}>🧹 Broom</option>
                                <option value="heart" {{ old('icon', $category->icon) === 'heart' ? 'selected' : '' }}>❤️ Heart</option>
                                <option value="wrench" {{ old('icon', $category->icon) === 'wrench' ? 'selected' : '' }}>🔧 Wrench</option>
                            </select>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Used in Services and app as category icon.</p>
                            @error('icon')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500">
                            <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Available in app</label>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 -mt-1">When unchecked, the app shows this category as &quot;Coming Soon&quot;.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button type="submit" 
                            class="px-6 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-sm">
                        Update Category
                    </button>
                    <a href="{{ route('admin.categories.index') }}" 
                       class="px-6 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        var fileInput = document.getElementById('image');
        var currentWrap = document.getElementById('current-image-wrap');
        var previewPlaceholder = document.getElementById('image-preview-edit');
        var previewImg = document.getElementById('image-preview-img');
        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    var reader = new FileReader();
                    reader.onload = function() {
                        previewImg.src = reader.result;
                        previewImg.alt = file.name;
                        previewImg.classList.remove('hidden');
                        if (currentWrap) currentWrap.classList.add('hidden');
                        if (previewPlaceholder) previewPlaceholder.classList.add('hidden');
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewImg.classList.add('hidden');
                    previewImg.src = '';
                    if (currentWrap) currentWrap.classList.remove('hidden');
                    if (previewPlaceholder) previewPlaceholder.classList.remove('hidden');
                }
            });
        }
    </script>
</x-admin-layout>




