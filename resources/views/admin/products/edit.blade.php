<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Dashboard</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <a href="{{ route('admin.products.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Products</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Edit product</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Edit product</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Update all product details — same options as Add product</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.show', $product) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">View</a>
                <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Cancel
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" id="productForm">
                @csrf
                @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content (Left Column - 2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- 1. Product Title & Description -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Product title <span class="text-red-500">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required placeholder="e.g. Acme Cotton T-Shirt"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100"
                                   oninput="generateHandle(this.value)">
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                            <textarea id="description" name="description" rows="6" placeholder="Describe your product..."
                                      class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">{{ old('description', $product->description) }}</textarea>
                            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- 2. Media (same layout as Add product: main image + thumb strip + Add more) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Media</h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">First image is the main product image. Add or replace photos — drag to reorder, or click a thumbnail to set as main.</p>
                            </div>
                            <a href="{{ route('admin.products.show', $product) }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg hover:bg-indigo-100 dark:hover:bg-indigo-900/30 transition-colors shrink-0" title="View product details page">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                View details
                            </a>
                        </div>
                        <div class="p-5">
                            <input type="file" id="imageInput" name="images[]" multiple accept="image/jpeg,image/jpg,image/png,image/webp" class="sr-only">

                            @php
                                $allImages = $product->images->sortBy('sort_order')->values();
                                $existingImages = collect(\App\Models\ProductImage::uniqueByPath($allImages))->sortBy('sort_order')->values();
                                $primaryExisting = $existingImages->firstWhere('is_primary', true) ?? $existingImages->first();
                            @endphp

                            @if($existingImages->isNotEmpty())
                                <!-- Existing images: large main + scrollable thumb strip (show ALL images) -->
                                <div id="existingMediaSection" class="mb-5">
                                    <div class="flex flex-col gap-4">
                                        <div class="w-full aspect-square max-w-md rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                            <img id="existingPrimaryImg" src="{{ $primaryExisting ? $primaryExisting->getImageUrl() : '' }}" alt="Main product image" class="w-full h-full object-cover">
                                        </div>
                                        <div class="space-y-1">
                                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400">All images ({{ $existingImages->count() }}) — scroll to see all</p>
                                            <div class="flex items-center gap-2 overflow-x-auto pb-2 -mx-1 scrollbar-thin" style="scrollbar-width: thin;">
                                                @foreach($existingImages as $img)
                                                    <div class="existing-thumb relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 cursor-pointer {{ $img->is_primary ? 'border-indigo-500 ring-2 ring-indigo-500/40' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500' }}" data-url="{{ $img->getImageUrl() }}" title="Click to set as main (upload new to replace)">
                                                        <img src="{{ $img->getImageUrl() }}" alt="" class="w-full h-full object-cover pointer-events-none">
                                                        @if($img->is_primary)<span class="absolute bottom-0 left-0 right-0 bg-indigo-600 text-white text-[10px] font-medium text-center py-0.5">Main</span>@endif
                                                    </div>
                                                @endforeach
                                                <label for="imageInput" class="flex-shrink-0 w-16 h-16 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600 flex flex-col items-center justify-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors gap-0.5 bg-transparent" title="Add more photos">
                                                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                    <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400">Add</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">To replace the main image, use "Replace main image" below. New files added here will be appended.</p>
                                </div>
                <div class="mb-4">
                                    <label for="main_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Replace main image (optional)</label>
                                    <label for="main_image" class="flex items-center justify-center gap-2 rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-sm text-gray-700 dark:text-gray-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14" /></svg>
                                        Choose file
                                    </label>
                                    <input type="file" id="main_image" name="main_image" accept="image/jpeg,image/jpg,image/png,image/webp" class="sr-only">
                                </div>
                            @else
                                <!-- No existing images: same empty state as create -->
                                <label id="imageUploadArea" for="imageInput" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700/30 min-h-[280px] cursor-pointer transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 block">
                                    <div class="flex flex-col items-center text-center pointer-events-none">
                                        <div class="w-16 h-16 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                        </div>
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Add product photos</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">or drop files here</p>
                                        <p id="dropHint" class="text-xs text-indigo-600 dark:text-indigo-400 mt-2 hidden font-medium">Drop to add</p>
                                    </div>
                                </label>
                            @endif

                            <!-- New uploads preview: large main + thumb strip (like create) -->
                            <div id="imagePreviewSection" class="hidden mt-4">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">New photos (first = main)</p>
                                <div class="flex flex-col gap-4">
                                    <div id="primaryPreviewWrap" class="w-full aspect-square max-w-md rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                        <img id="primaryPreviewImg" src="" alt="Main product image" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div id="imagePreviewGrid" class="flex flex-wrap gap-2 items-center"></div>
                                        <button type="button" id="addMoreBtn" class="flex-shrink-0 w-16 h-16 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600 flex flex-col items-center justify-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors gap-0.5 bg-transparent" title="Add more photos">
                                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400">Add</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Pricing -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Pricing</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Price <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-500 text-sm">AED</span></div>
                                    <input type="number" id="price" name="price" value="{{ old('price', $product->price) }}" step="0.01" min="0" required placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                </div>
                                @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="compare_at_price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Compare at price</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-500 text-sm">AED</span></div>
                                    <input type="number" id="compare_at_price" name="compare_at_price" value="{{ old('compare_at_price', $product->compare_at_price) }}" step="0.01" min="0" placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Show customers how much they're saving</p>
                            </div>
                            <div>
                                <label for="cost_per_item" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Cost per item</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><span class="text-gray-500 text-sm">AED</span></div>
                                    <input type="number" id="cost_per_item" name="cost_per_item" value="{{ old('cost_per_item', $product->cost_per_item) }}" step="0.01" min="0" placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Customers won't see this</p>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Inventory -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Inventory</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="sku" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">SKU (Stock Keeping Unit)</label>
                                    <input type="text" id="sku" name="sku" value="{{ old('sku', $product->sku) }}" placeholder="e.g. SKU-123"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                    @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="barcode" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Barcode (ISBN, UPC, GTIN)</label>
                                    <input type="text" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="e.g. 1234567890123"
                                           class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                </div>
                            </div>
                            <div>
                                <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Quantity</label>
                                <input type="number" id="stock" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" min="0" placeholder="0"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" id="track_quantity" name="track_quantity" value="1" {{ old('track_quantity', $product->track_quantity ?? true) ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="track_quantity" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Track quantity</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="allow_backorder" name="allow_backorder" value="1" {{ old('allow_backorder', $product->allow_backorder) ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="allow_backorder" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Continue selling when out of stock</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Shipping -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Shipping</h2>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" id="requires_shipping" name="requires_shipping" value="1" {{ old('requires_shipping', $product->requires_shipping ?? true) ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="requires_shipping" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">This is a physical product</label>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="weight" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Weight</label>
                                    <div class="flex gap-2">
                                        <input type="text" id="weight" name="weight" value="{{ old('weight', $product->weight) }}" placeholder="0.0" class="flex-1 rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                        <select id="weight_unit" name="weight_unit" class="rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                            <option value="kg" {{ old('weight_unit', $product->weight_unit ?? 'kg') == 'kg' ? 'selected' : '' }}>kg</option>
                                            <option value="g" {{ old('weight_unit', $product->weight_unit) == 'g' ? 'selected' : '' }}>g</option>
                                            <option value="lb" {{ old('weight_unit', $product->weight_unit) == 'lb' ? 'selected' : '' }}>lb</option>
                                            <option value="oz" {{ old('weight_unit', $product->weight_unit) == 'oz' ? 'selected' : '' }}>oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 6. Search Engine Listing -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Search engine listing preview</h2>
                        <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="text-sm">
                                <div class="text-blue-600 dark:text-blue-400 font-medium mb-1" id="previewTitle">{{ old('meta_title', $product->meta_title) ?: 'Add a title to see a preview' }}</div>
                                <div class="text-green-600 dark:text-green-400 text-xs mb-1" id="previewUrl">{{ url('/products') }}/<span id="previewHandle">{{ old('handle', $product->handle) ?: 'product-handle' }}</span></div>
                                <div class="text-gray-600 dark:text-gray-400 text-xs" id="previewDescription">{{ old('meta_description', $product->meta_description) ?: 'Add a description to see a preview' }}</div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="meta_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Page title</label>
                                <input type="text" id="meta_title" name="meta_title" value="{{ old('meta_title', $product->meta_title) }}" maxlength="60" placeholder="Product title"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100" oninput="updatePreview('title', this.value)">
                                <p class="text-xs text-gray-500 mt-1"><span id="titleCount">{{ strlen(old('meta_title', $product->meta_title ?? '')) }}</span>/60 characters</p>
                            </div>
                            <div>
                                <label for="meta_description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Meta description</label>
                                <textarea id="meta_description" name="meta_description" rows="3" maxlength="160" placeholder="Brief description for search results"
                                          class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100"
                                          oninput="updatePreview('description', this.value)">{{ old('meta_description', $product->meta_description) }}</textarea>
                                <p class="text-xs text-gray-500 mt-1"><span id="descriptionCount">{{ strlen(old('meta_description', $product->meta_description ?? '')) }}</span>/160 characters</p>
                            </div>
                            <div>
                                <label for="handle" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL and handle</label>
                                <div class="flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-500 text-sm">{{ url('/products') }}/</span>
                                    <input type="text" id="handle" name="handle" value="{{ old('handle', $product->handle) }}" placeholder="product-handle"
                                           class="flex-1 rounded-r-md border-gray-300 dark:border-gray-600 focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100" oninput="updatePreview('handle', this.value)">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate from title</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar (Right Column - 1/3) -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Status -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Status</h2>
                        <select id="status" name="status" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                            <option value="draft" {{ old('status', $product->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ old('status', $product->status) == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="archived" {{ old('status', $product->status) == 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>

                    <!-- Product Organization -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Product organization</h2>
                        <div class="space-y-4">
                    <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category</label>
                                <select id="category_id" name="category_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                    <option value="">No category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                            </div>
                            <div>
                                <label for="service_ids" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Services (optional)</label>
                                <select id="service_ids" name="service_ids[]" multiple
                                        class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100 min-h-[100px]">
                                    @foreach($services ?? [] as $svc)
                                        <option value="{{ $svc->id }}" {{ in_array($svc->id, old('service_ids', $product->services->pluck('id')->all())) ? 'selected' : '' }}>{{ $svc->name }}@if($svc->category) ({{ $svc->category->name }})@endif</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Hold Ctrl/Cmd to select multiple.</p>
                            </div>
                            <div>
                                <label for="vendor" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vendor</label>
                                <input type="text" id="vendor" name="vendor" value="{{ old('vendor', $product->vendor) }}" placeholder="e.g. Acme Corp"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Product type</label>
                                <input type="text" id="type" name="type" value="{{ old('type', $product->type) }}" placeholder="e.g. T-Shirt"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                            </div>
                            <div>
                                <label for="tags" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tags</label>
                                <input type="text" id="tags" name="tags" value="{{ old('tags', $product->tags) }}" placeholder="e.g. summer, sale, new"
                                       class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">
                                <p class="text-xs text-gray-500 mt-1">Separate tags with commas</p>
                            </div>
                    </div>
                </div>

                    <!-- Tax -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Tax</h2>
                        <div class="flex items-center">
                            <input type="checkbox" id="taxable" name="taxable" value="1" {{ old('taxable', $product->taxable ?? true) ? 'checked' : '' }} class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="taxable" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Charge tax on this product</label>
                </div>
                </div>

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div class="space-y-3">
                            <button type="submit" id="submitProductBtn" class="w-full px-4 py-2.5 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded-md hover:bg-indigo-700 dark:hover:bg-indigo-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                Update product
                            </button>
                            <a href="{{ route('admin.products.index') }}" class="block w-full text-center px-4 py-2.5 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-medium rounded-md border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
                </div>
            </form>
    </div>

    @push('scripts')
    <script>
        (function() {
            var form = document.getElementById('productForm');
            if (form) form.addEventListener('submit', function() {
                var btn = document.getElementById('submitProductBtn');
                if (btn) { btn.disabled = true; btn.textContent = 'Updating...'; }
            });

            var uploadedImages = [];
            var imageInput = document.getElementById('imageInput');
            var imageUploadArea = document.getElementById('imageUploadArea');
            var imagePreviewSection = document.getElementById('imagePreviewSection');
            var imagePreviewGrid = document.getElementById('imagePreviewGrid');
            var primaryPreviewImg = document.getElementById('primaryPreviewImg');
            var addMoreBtn = document.getElementById('addMoreBtn');

            function addFiles(files) {
                var list = Array.from(files || []).filter(function(f) { return f.type && f.type.indexOf('image/') === 0; });
                if (list.length === 0) return;
                var pending = list.length;
                list.forEach(function(file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        uploadedImages.push({ file: file, preview: e.target.result });
                        if (--pending === 0) {
                            updateImagePreview();
                            syncFileInput();
                        }
                    };
                    reader.readAsDataURL(file);
                });
            }
            function syncFileInput() {
                var dt = new DataTransfer();
                uploadedImages.forEach(function(img) { dt.items.add(img.file); });
                if (imageInput) imageInput.files = dt.files;
            }
            function updateImagePreview() {
                if (uploadedImages.length > 0) {
                    if (imageUploadArea) imageUploadArea.classList.add('hidden');
                    imagePreviewSection.classList.remove('hidden');
                    if (primaryPreviewImg) primaryPreviewImg.src = uploadedImages[0].preview;
                    imagePreviewGrid.innerHTML = uploadedImages.map(function(img, index) {
                        var isPrimary = index === 0;
                        return '<div class="media-thumb relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 cursor-move ' + (isPrimary ? 'border-indigo-500 ring-2 ring-indigo-500/40' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500') + '" data-index="' + index + '" draggable="true" title="Drag to reorder, click to set as main">' +
                            '<img src="' + img.preview + '" alt="" class="w-full h-full object-cover pointer-events-none">' +
                            '<span class="absolute top-0.5 left-0.5 w-5 h-5 flex items-center justify-center bg-black/50 rounded cursor-move text-white text-[10px] font-bold leading-none">⋮⋮</span>' +
                            (isPrimary ? '<span class="absolute bottom-0 left-0 right-0 bg-indigo-600 text-white text-[10px] font-medium text-center py-0.5">Main</span>' : '') +
                            '<button type="button" class="media-remove absolute top-0.5 right-0.5 w-5 h-5 flex items-center justify-center bg-red-500 text-white rounded-full opacity-0 hover:opacity-100 focus:opacity-100 transition-opacity" data-index="' + index + '" title="Remove"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>' +
                            '</div>';
                    }).join('');
                    bindMediaButtons();
                    bindThumbDrag();
                } else {
                    imagePreviewSection.classList.add('hidden');
                    if (imageUploadArea) imageUploadArea.classList.remove('hidden');
                    if (primaryPreviewImg) primaryPreviewImg.src = '';
                }
            }
            function bindMediaButtons() {
                if (!imagePreviewGrid) return;
                imagePreviewGrid.querySelectorAll('.media-thumb').forEach(function(thumb) {
                    thumb.addEventListener('click', function(e) {
                        if (e.target.closest('.media-remove')) return;
                        var idx = parseInt(thumb.getAttribute('data-index'), 10);
                        if (idx !== 0) setPrimary(idx);
                    });
                });
                imagePreviewGrid.querySelectorAll('.media-remove').forEach(function(btn) {
                    btn.onclick = function(e) { e.preventDefault(); e.stopPropagation(); removeImage(parseInt(btn.getAttribute('data-index'), 10)); };
                });
            }
            var draggedIndex = null;
            function bindThumbDrag() {
                if (!imagePreviewGrid) return;
                var thumbs = imagePreviewGrid.querySelectorAll('.media-thumb');
                thumbs.forEach(function(thumb) {
                    thumb.setAttribute('draggable', 'true');
                    thumb.ondragstart = function(e) {
                        draggedIndex = parseInt(thumb.getAttribute('data-index'), 10);
                        e.dataTransfer.setData('text/plain', draggedIndex);
                        e.dataTransfer.effectAllowed = 'move';
                        thumb.classList.add('opacity-50');
                    };
                    thumb.ondragend = function() { thumb.classList.remove('opacity-50'); draggedIndex = null; };
                    thumb.ondragover = function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        var idx = parseInt(thumb.getAttribute('data-index'), 10);
                        if (draggedIndex !== null && draggedIndex !== idx) thumb.classList.add('ring-2', 'ring-indigo-400');
                    };
                    thumb.ondragleave = function() { thumb.classList.remove('ring-2', 'ring-indigo-400'); };
                    thumb.ondrop = function(e) {
                        e.preventDefault();
                        thumb.classList.remove('ring-2', 'ring-indigo-400');
                        var toIndex = parseInt(thumb.getAttribute('data-index'), 10);
                        if (draggedIndex === null || draggedIndex === toIndex) { draggedIndex = null; return; }
                        var item = uploadedImages.splice(draggedIndex, 1)[0];
                        uploadedImages.splice(toIndex, 0, item);
                        draggedIndex = null;
                        syncFileInput();
                        updateImagePreview();
                    };
                });
            }
            function setPrimary(index) {
                if (index <= 0) return;
                var item = uploadedImages.splice(index, 1)[0];
                uploadedImages.unshift(item);
                syncFileInput();
                updateImagePreview();
            }
            function removeImage(index) {
                uploadedImages.splice(index, 1);
                syncFileInput();
                updateImagePreview();
            }
            function openFilePicker() { if (imageInput) imageInput.click(); }

            if (imageInput) imageInput.addEventListener('change', function(e) { addFiles(e.target.files); e.target.value = ''; });
            if (addMoreBtn) addMoreBtn.addEventListener('click', function(e) { e.preventDefault(); openFilePicker(); });
            if (imageUploadArea) {
                imageUploadArea.addEventListener('dragover', function(e) { e.preventDefault(); this.classList.add('border-indigo-500', 'bg-indigo-50/50', 'dark:bg-indigo-900/20'); var h = document.getElementById('dropHint'); if (h) h.classList.remove('hidden'); });
                imageUploadArea.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('border-indigo-500', 'bg-indigo-50/50', 'dark:bg-indigo-900/20'); var h = document.getElementById('dropHint'); if (h) h.classList.add('hidden'); });
                imageUploadArea.addEventListener('drop', function(e) { e.preventDefault(); this.classList.remove('border-indigo-500', 'bg-indigo-50/50', 'dark:bg-indigo-900/20'); var h = document.getElementById('dropHint'); if (h) h.classList.add('hidden'); addFiles(e.dataTransfer.files); });
            }
            if (addMoreBtn) {
                addMoreBtn.addEventListener('dragover', function(e) { e.preventDefault(); e.stopPropagation(); this.classList.add('border-indigo-500', 'bg-indigo-50/50'); });
                addMoreBtn.addEventListener('dragleave', function(e) { e.preventDefault(); this.classList.remove('border-indigo-500', 'bg-indigo-50/50'); });
                addMoreBtn.addEventListener('drop', function(e) { e.preventDefault(); e.stopPropagation(); this.classList.remove('border-indigo-500', 'bg-indigo-50/50'); addFiles(e.dataTransfer.files); });
            }
            document.querySelectorAll('.existing-thumb').forEach(function(el) {
                el.addEventListener('click', function() {
                    var url = this.getAttribute('data-url');
                    var big = document.getElementById('existingPrimaryImg');
                    if (big && url) big.src = url;
                });
            });
            var mainInput = document.getElementById('main_image');
            if (mainInput) mainInput.addEventListener('change', function(e) {
                var f = e.target.files[0];
                if (!f || f.type.indexOf('image/') !== 0) return;
                var reader = new FileReader();
                reader.onload = function(ev) {
                    var big = document.getElementById('existingPrimaryImg');
                    if (big) big.src = ev.target.result;
                };
                reader.readAsDataURL(f);
            });
        })();
        function generateHandle(name) {
            var handleEl = document.getElementById('handle');
            if (handleEl && !handleEl.value && name) {
                var handle = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                handleEl.value = handle;
                updatePreview('handle', handle);
            }
        }
        function updatePreview(type, value) {
            if (type === 'title') {
                document.getElementById('previewTitle').textContent = value || 'Add a title to see a preview';
                document.getElementById('titleCount').textContent = (value || '').length;
            } else if (type === 'description') {
                document.getElementById('previewDescription').textContent = value || 'Add a description to see a preview';
                document.getElementById('descriptionCount').textContent = (value || '').length;
            } else if (type === 'handle') {
                document.getElementById('previewHandle').textContent = value || 'product-handle';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            var metaTitle = document.getElementById('meta_title');
            var metaDescription = document.getElementById('meta_description');
            if (metaTitle && metaTitle.value) updatePreview('title', metaTitle.value);
            if (metaDescription && metaDescription.value) updatePreview('description', metaDescription.value);
        });
    </script>
    @endpush
</x-admin-layout>
