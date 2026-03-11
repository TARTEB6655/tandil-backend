<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">{{ __('admin.dashboard') }}</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <a href="{{ route('admin.products.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">{{ __('admin.products') }}</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ __('admin.create_new_product') }}</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ __('admin.create_new_product') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.create_product_description') }}</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.index') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    {{ __('admin.cancel') }}
                </a>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" id="productForm">
            @csrf
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content (Left Column - 2/3) — Shopify order: Title → Description → Media → rest -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- 1. Product Title & Description (top, like Shopify) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Product title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}" 
                                   required
                                   placeholder="e.g. Acme Cotton T-Shirt"
                                   class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100"
                                   oninput="generateHandle(this.value)">
                            @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="6"
                                      placeholder="Describe your product..."
                                      class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-gray-100">{{ old('description') }}</textarea>
                            @error('description') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- 2. Media (after title & description, like Shopify) -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">Media</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">First image is the main product image. Drag to reorder, or click a thumbnail to set as main.</p>
                        </div>
                        <div class="p-5">
                            <input type="file" id="imageInput" name="images[]" multiple accept="image/jpeg,image/jpg,image/png,image/webp" class="sr-only">

                            <!-- Empty state: label opens file picker (works without JS) -->
                            <label id="imageUploadArea" for="imageInput" class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-600 bg-gray-50/50 dark:bg-gray-700/30 min-h-[280px] cursor-pointer transition-colors hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 block">
                                <div class="flex flex-col items-center text-center pointer-events-none">
                                    <div class="w-16 h-16 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.add_product_photos') }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">or drop files here</p>
                                    <p id="dropHint" class="text-xs text-indigo-600 dark:text-indigo-400 mt-2 hidden font-medium">Drop to add</p>
                                </div>
                            </label>

                            <!-- With images: Shopify-style — large main + thumbnail strip (drag to reorder, click to set main) -->
                            <div id="imagePreviewSection" class="hidden">
                                <div class="flex flex-col gap-4">
                                    <!-- Large main image (like Shopify) -->
                                    <div id="primaryPreviewWrap" class="w-full aspect-square max-w-md rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700">
                                        <img id="primaryPreviewImg" src="" alt="Main product image" class="w-full h-full object-cover">
                                    </div>
                                    <!-- Thumbnail strip: drag to reorder, click to set main, + Add more -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div id="imagePreviewGrid" class="flex flex-wrap gap-2 items-center">
                                            <!-- Thumbs filled by JS (draggable, clickable) -->
                                        </div>
                                        <button type="button" id="addMoreBtn" class="flex-shrink-0 w-16 h-16 rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-600 flex flex-col items-center justify-center cursor-pointer hover:border-indigo-400 hover:bg-indigo-50/50 dark:hover:bg-indigo-900/10 transition-colors gap-0.5 bg-transparent" title="Add more photos">
                                            <svg class="w-6 h-6 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400">Add</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Pricing</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                                    Price <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">AED</span>
                                    </div>
                                    <input type="number" 
                                           id="price" 
                                           name="price" 
                                           value="{{ old('price') }}" 
                                           step="0.01" 
                                           min="0" 
                                           required
                                           placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                @error('price') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label for="compare_at_price" class="block text-sm font-medium text-gray-700 mb-2">
                                    Compare at price
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">AED</span>
                                    </div>
                                    <input type="number" 
                                           id="compare_at_price" 
                                           name="compare_at_price" 
                                           value="{{ old('compare_at_price') }}" 
                                           step="0.01" 
                                           min="0"
                                           placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Show customers how much they're saving</p>
                            </div>

                            <div>
                                <label for="cost_per_item" class="block text-sm font-medium text-gray-700 mb-2">
                                    Cost per item
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">AED</span>
                                    </div>
                                    <input type="number" 
                                           id="cost_per_item" 
                                           name="cost_per_item" 
                                           value="{{ old('cost_per_item') }}" 
                                           step="0.01" 
                                           min="0"
                                           placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Customers won't see this</p>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Inventory</h2>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                                        SKU (Stock Keeping Unit)
                                    </label>
                                    <input type="text" 
                                           id="sku" 
                                           name="sku" 
                                           value="{{ old('sku') }}"
                                           placeholder="e.g. SKU-123"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('sku') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="barcode" class="block text-sm font-medium text-gray-700 mb-2">
                                        Barcode (ISBN, UPC, GTIN, etc.)
                                    </label>
                                    <input type="text" 
                                           id="barcode" 
                                           name="barcode" 
                                           value="{{ old('barcode') }}"
                                           placeholder="e.g. 1234567890123"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>

                            <div>
                                <label for="stock" class="block text-sm font-medium text-gray-700 mb-2">
                                    Quantity
                                </label>
                                <input type="number" 
                                       id="stock" 
                                       name="stock" 
                                       value="{{ old('stock', 0) }}"
                                       min="0"
                                       placeholder="0"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="track_quantity" 
                                           name="track_quantity" 
                                           value="1"
                                           {{ old('track_quantity', true) ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="track_quantity" class="ml-2 block text-sm text-gray-900">
                                        Track quantity
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" 
                                           id="allow_backorder" 
                                           name="allow_backorder" 
                                           value="1"
                                           {{ old('allow_backorder') ? 'checked' : '' }}
                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="allow_backorder" class="ml-2 block text-sm text-gray-900">
                                        Continue selling when out of stock
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Shipping</h2>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="requires_shipping" 
                                       name="requires_shipping" 
                                       value="1"
                                       {{ old('requires_shipping', true) ? 'checked' : '' }}
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="requires_shipping" class="ml-2 block text-sm text-gray-900">
                                    This is a physical product
                                </label>
                            </div>

                            <div id="shippingDetails" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">
                                        Weight
                                    </label>
                                    <div class="flex gap-2">
                                        <input type="text" 
                                               id="weight" 
                                               name="weight" 
                                               value="{{ old('weight') }}"
                                               placeholder="0.0"
                                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <select id="weight_unit" 
                                                name="weight_unit"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="kg" {{ old('weight_unit', 'kg') == 'kg' ? 'selected' : '' }}>kg</option>
                                            <option value="g" {{ old('weight_unit') == 'g' ? 'selected' : '' }}>g</option>
                                            <option value="lb" {{ old('weight_unit') == 'lb' ? 'selected' : '' }}>lb</option>
                                            <option value="oz" {{ old('weight_unit') == 'oz' ? 'selected' : '' }}>oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Engine Listing -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Search engine listing preview</h2>
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-sm">
                                <div class="text-blue-600 font-medium mb-1" id="previewTitle">{{ old('meta_title') ?: 'Add a title to see a preview' }}</div>
                                <div class="text-green-600 text-xs mb-1" id="previewUrl">{{ url('/products') }}/<span id="previewHandle">{{ old('handle') ?: 'product-handle' }}</span></div>
                                <div class="text-gray-600 text-xs" id="previewDescription">{{ old('meta_description') ?: 'Add a description to see a preview' }}</div>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="meta_title" class="block text-sm font-medium text-gray-700 mb-2">
                                    Page title
                                </label>
                                <input type="text" 
                                       id="meta_title" 
                                       name="meta_title" 
                                       value="{{ old('meta_title') }}"
                                       maxlength="60"
                                       placeholder="Product title"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       oninput="updatePreview('title', this.value)">
                                <p class="text-xs text-gray-500 mt-1"><span id="titleCount">0</span>/60 characters</p>
                            </div>

                            <div>
                                <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-2">
                                    Meta description
                                </label>
                                <textarea id="meta_description" 
                                          name="meta_description" 
                                          rows="3"
                                          maxlength="160"
                                          placeholder="Brief description for search results"
                                          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                          oninput="updatePreview('description', this.value)">{{ old('meta_description') }}</textarea>
                                <p class="text-xs text-gray-500 mt-1"><span id="descriptionCount">0</span>/160 characters</p>
                            </div>

                            <div>
                                <label for="handle" class="block text-sm font-medium text-gray-700 mb-2">
                                    URL and handle
                                </label>
                                <div class="flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        {{ url('/products') }}/
                                    </span>
                                    <input type="text" 
                                           id="handle" 
                                           name="handle" 
                                           value="{{ old('handle') }}"
                                           placeholder="product-handle"
                                           class="flex-1 rounded-r-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                           oninput="updatePreview('handle', this.value)">
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
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Status</h2>
                        <select id="status" 
                                name="status"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>

                    <!-- Product Organization -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Product organization</h2>
                        <div class="space-y-4">
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Category
                                </label>
                                <select id="category_id" 
                                        name="category_id"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">No category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" {{ old('category_id', request('category_id')) == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="service_ids" class="block text-sm font-medium text-gray-700 mb-2">Services (optional)</label>
                                <select id="service_ids" name="service_ids[]" multiple
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[100px]">
                                    @foreach($services ?? [] as $svc)
                                        <option value="{{ $svc->id }}" {{ in_array($svc->id, old('service_ids', request('service_id') ? [request('service_id')] : [])) ? 'selected' : '' }}>{{ $svc->name }}@if($svc->category) ({{ $svc->category->name }})@endif</option>
                                    @endforeach
                                </select>
                                <p class="text-xs text-gray-500 mt-1">Hold Ctrl/Cmd to select multiple. Link this product to one or more services.</p>
                            </div>

                            <div>
                                <label for="vendor" class="block text-sm font-medium text-gray-700 mb-2">
                                    Vendor
                                </label>
                                <input type="text" 
                                       id="vendor" 
                                       name="vendor" 
                                       value="{{ old('vendor') }}"
                                       placeholder="e.g. Acme Corp"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">
                                    Product type
                                </label>
                                <input type="text" 
                                       id="type" 
                                       name="type" 
                                       value="{{ old('type') }}"
                                       placeholder="e.g. T-Shirt"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <div>
                                <label for="tags" class="block text-sm font-medium text-gray-700 mb-2">
                                    Tags
                                </label>
                                <input type="text" 
                                       id="tags" 
                                       name="tags" 
                                       value="{{ old('tags') }}"
                                       placeholder="e.g. summer, sale, new"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Separate tags with commas</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tax -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Tax</h2>
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="taxable" 
                                   name="taxable" 
                                   value="1"
                                   {{ old('taxable', true) ? 'checked' : '' }}
                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="taxable" class="ml-2 block text-sm text-gray-900">
                                Charge tax on this product
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <div class="space-y-3">
                            <button type="submit" id="submitProductBtn"
                                    class="w-full px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                Save product
                            </button>
                            <a href="{{ route('admin.products.index') }}" 
                               class="block w-full text-center px-4 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
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
            var uploadedImages = [];
            var imageInput = document.getElementById('imageInput');
            var imageUploadArea = document.getElementById('imageUploadArea');
            var imagePreviewSection = document.getElementById('imagePreviewSection');
            var imagePreviewGrid = document.getElementById('imagePreviewGrid');
            var primaryPreviewImg = document.getElementById('primaryPreviewImg');

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
                imageInput.files = dt.files;
            }

            function handleImageUpload(event) {
                addFiles(event.target.files);
                event.target.value = '';
            }

            function updateImagePreview() {
                if (uploadedImages.length > 0) {
                    imageUploadArea.classList.add('hidden');
                    imagePreviewSection.classList.remove('hidden');
                    if (primaryPreviewImg) primaryPreviewImg.src = uploadedImages[0].preview;
                    imagePreviewGrid.innerHTML = uploadedImages.map(function(img, index) {
                        var isPrimary = index === 0;
                        return '<div class="media-thumb relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 cursor-move ' + (isPrimary ? 'border-indigo-500 ring-2 ring-indigo-500/40' : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500') + '" data-index="' + index + '" draggable="true" title="Drag to reorder, click to set as main">' +
                            '<img src="' + img.preview + '" alt="" class="w-full h-full object-cover pointer-events-none">' +
                            '<span class="absolute top-0.5 left-0.5 w-5 h-5 flex items-center justify-center bg-black/50 rounded cursor-move text-white text-[10px] font-bold leading-none" title="Drag to reorder">⋮⋮</span>' +
                            (isPrimary ? '<span class="absolute bottom-0 left-0 right-0 bg-indigo-600 text-white text-[10px] font-medium text-center py-0.5">Main</span>' : '') +
                            '<button type="button" class="media-remove absolute top-0.5 right-0.5 w-5 h-5 flex items-center justify-center bg-red-500 text-white rounded-full opacity-0 hover:opacity-100 focus:opacity-100 transition-opacity" data-index="' + index + '" title="Remove"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>' +
                            '</div>';
                    }).join('');
                    bindMediaButtons();
                    bindThumbDrag();
                } else {
                    imagePreviewSection.classList.add('hidden');
                    imageUploadArea.classList.remove('hidden');
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
                    thumb.ondragend = function() {
                        thumb.classList.remove('opacity-50');
                        draggedIndex = null;
                    };
                    thumb.ondragover = function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';
                        var idx = parseInt(thumb.getAttribute('data-index'), 10);
                        if (draggedIndex !== null && draggedIndex !== idx) thumb.classList.add('ring-2', 'ring-indigo-400');
                    };
                    thumb.ondragleave = function() {
                        thumb.classList.remove('ring-2', 'ring-indigo-400');
                    };
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

            function moveImage(index, direction) {
                var newIndex = index + direction;
                if (newIndex < 0 || newIndex >= uploadedImages.length) return;
                var item = uploadedImages.splice(index, 1)[0];
                uploadedImages.splice(newIndex, 0, item);
                syncFileInput();
                updateImagePreview();
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

            function openFilePicker() {
                if (imageInput) imageInput.click();
            }

            function setupDropZone(el, onDrop) {
                if (!el) return;
                if (el.tagName !== 'LABEL') {
                    el.addEventListener('click', function(e) {
                        if (e.target.tagName === 'BUTTON' || e.target.closest('button')) return;
                        openFilePicker();
                    });
                }
                el.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.add('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/20');
                    var hint = document.getElementById('dropHint');
                    if (hint) hint.classList.remove('hidden');
                });
                el.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    el.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/20');
                    var hint = document.getElementById('dropHint');
                    if (hint) hint.classList.add('hidden');
                });
                el.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    el.classList.remove('border-indigo-500', 'bg-indigo-50', 'dark:bg-indigo-900/20');
                    var hint = document.getElementById('dropHint');
                    if (hint) hint.classList.add('hidden');
                    if (e.dataTransfer.files && e.dataTransfer.files.length) onDrop(e.dataTransfer.files);
                });
            }

            function initMediaUpload() {
                imageInput = document.getElementById('imageInput');
                imageUploadArea = document.getElementById('imageUploadArea');
                imagePreviewSection = document.getElementById('imagePreviewSection');
                imagePreviewGrid = document.getElementById('imagePreviewGrid');
                primaryPreviewImg = document.getElementById('primaryPreviewImg');
                var addMoreBtnEl = document.getElementById('addMoreBtn');
                if (!imageInput || !imageUploadArea) return;
                imageInput.addEventListener('change', handleImageUpload);
                if (addMoreBtnEl) {
                    addMoreBtnEl.addEventListener('click', function(e) { e.preventDefault(); openFilePicker(); });
                    setupDropZone(addMoreBtnEl, addFiles);
                }
                setupDropZone(imageUploadArea, addFiles);
            }

            var form = document.getElementById('productForm');
            if (form) form.addEventListener('submit', function() {
                var btn = document.getElementById('submitProductBtn');
                if (btn) { btn.disabled = true; btn.textContent = 'Saving...'; }
            });

            window.moveImage = moveImage;
            window.setPrimary = setPrimary;
            window.removeImage = removeImage;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initMediaUpload);
            } else {
                initMediaUpload();
            }
        })();

        function generateHandle(name) {
            if (!document.getElementById('handle').value) {
                const handle = name.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/(^-|-$)/g, '');
                document.getElementById('handle').value = handle;
                updatePreview('handle', handle);
            }
        }

        function updatePreview(type, value) {
            if (type === 'title') {
                document.getElementById('previewTitle').textContent = value || 'Add a title to see a preview';
                document.getElementById('titleCount').textContent = value.length;
            } else if (type === 'description') {
                document.getElementById('previewDescription').textContent = value || 'Add a description to see a preview';
                document.getElementById('descriptionCount').textContent = value.length;
            } else if (type === 'handle') {
                document.getElementById('previewHandle').textContent = value || 'product-handle';
            }
        }

        // Initialize character counts
        document.addEventListener('DOMContentLoaded', function() {
            const metaTitle = document.getElementById('meta_title');
            const metaDescription = document.getElementById('meta_description');
            if (metaTitle.value) updatePreview('title', metaTitle.value);
            if (metaDescription.value) updatePreview('description', metaDescription.value);
        });
    </script>
    @endpush
</x-admin-layout>
