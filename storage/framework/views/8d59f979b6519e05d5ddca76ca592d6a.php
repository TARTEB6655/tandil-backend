<?php if (isset($component)) { $__componentOriginale0f1cdd055772eb1d4a99981c240763e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale0f1cdd055772eb1d4a99981c240763e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-layout','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Add product</h1>
                <p class="mt-1 text-sm text-gray-500">Create a new product to add to your store</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo e(route('admin.products.index')); ?>" 
                   class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </a>
            </div>
        </div>

        <form method="POST" action="<?php echo e(route('admin.products.store')); ?>" enctype="multipart/form-data" id="productForm">
            <?php echo csrf_field(); ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content (Left Column - 2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Product Images -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Media</h2>
                        <div class="space-y-4">
                            <!-- Image Upload Area -->
                            <div id="imageUploadArea" class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-indigo-400 transition-colors">
                                <input type="file" 
                                       id="imageInput" 
                                       name="images[]" 
                                       multiple 
                                       accept="image/*" 
                                       class="hidden"
                                       onchange="handleImageUpload(event)">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-600 mb-2">Add images to your product</p>
                                    <button type="button" 
                                            onclick="document.getElementById('imageInput').click()"
                                            class="px-4 py-2 text-sm font-medium text-indigo-600 hover:text-indigo-700">
                                        Add images
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Image Preview Grid -->
                            <div id="imagePreviewGrid" class="grid grid-cols-4 gap-4 hidden">
                                <!-- Images will be dynamically added here -->
                            </div>
                        </div>
                    </div>

                    <!-- Product Title & Description -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Product title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" 
                                   id="name" 
                                   name="name" 
                                   value="<?php echo e(old('name')); ?>" 
                                   required
                                   placeholder="e.g. Acme Cotton T-Shirt"
                                   class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                   oninput="generateHandle(this.value)">
                            <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Description
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      rows="6"
                                      placeholder="Describe your product..."
                                      class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><?php echo e(old('description')); ?></textarea>
                            <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
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
                                           value="<?php echo e(old('price')); ?>" 
                                           step="0.01" 
                                           min="0" 
                                           required
                                           placeholder="0.00"
                                           class="block w-full pl-12 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                                <?php $__errorArgs = ['price'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
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
                                           value="<?php echo e(old('compare_at_price')); ?>" 
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
                                           value="<?php echo e(old('cost_per_item')); ?>" 
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
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
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
                                           value="<?php echo e(old('sku')); ?>"
                                           placeholder="e.g. SKU-123"
                                           class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <?php $__errorArgs = ['sku'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                </div>

                                <div>
                                    <label for="barcode" class="block text-sm font-medium text-gray-700 mb-2">
                                        Barcode (ISBN, UPC, GTIN, etc.)
                                    </label>
                                    <input type="text" 
                                           id="barcode" 
                                           name="barcode" 
                                           value="<?php echo e(old('barcode')); ?>"
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
                                       value="<?php echo e(old('stock', 0)); ?>"
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
                                           <?php echo e(old('track_quantity', true) ? 'checked' : ''); ?>

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
                                           <?php echo e(old('allow_backorder') ? 'checked' : ''); ?>

                                           class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="allow_backorder" class="ml-2 block text-sm text-gray-900">
                                        Continue selling when out of stock
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Shipping -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Shipping</h2>
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="requires_shipping" 
                                       name="requires_shipping" 
                                       value="1"
                                       <?php echo e(old('requires_shipping', true) ? 'checked' : ''); ?>

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
                                               value="<?php echo e(old('weight')); ?>"
                                               placeholder="0.0"
                                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <select id="weight_unit" 
                                                name="weight_unit"
                                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <option value="kg" <?php echo e(old('weight_unit', 'kg') == 'kg' ? 'selected' : ''); ?>>kg</option>
                                            <option value="g" <?php echo e(old('weight_unit') == 'g' ? 'selected' : ''); ?>>g</option>
                                            <option value="lb" <?php echo e(old('weight_unit') == 'lb' ? 'selected' : ''); ?>>lb</option>
                                            <option value="oz" <?php echo e(old('weight_unit') == 'oz' ? 'selected' : ''); ?>>oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Engine Listing -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Search engine listing preview</h2>
                        <div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-sm">
                                <div class="text-blue-600 font-medium mb-1" id="previewTitle"><?php echo e(old('meta_title') ?: 'Add a title to see a preview'); ?></div>
                                <div class="text-green-600 text-xs mb-1" id="previewUrl"><?php echo e(url('/products')); ?>/<span id="previewHandle"><?php echo e(old('handle') ?: 'product-handle'); ?></span></div>
                                <div class="text-gray-600 text-xs" id="previewDescription"><?php echo e(old('meta_description') ?: 'Add a description to see a preview'); ?></div>
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
                                       value="<?php echo e(old('meta_title')); ?>"
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
                                          oninput="updatePreview('description', this.value)"><?php echo e(old('meta_description')); ?></textarea>
                                <p class="text-xs text-gray-500 mt-1"><span id="descriptionCount">0</span>/160 characters</p>
                            </div>

                            <div>
                                <label for="handle" class="block text-sm font-medium text-gray-700 mb-2">
                                    URL and handle
                                </label>
                                <div class="flex rounded-md shadow-sm">
                                    <span class="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                        <?php echo e(url('/products')); ?>/
                                    </span>
                                    <input type="text" 
                                           id="handle" 
                                           name="handle" 
                                           value="<?php echo e(old('handle')); ?>"
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
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Status</h2>
                        <select id="status" 
                                name="status"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="draft" <?php echo e(old('status', 'draft') == 'draft' ? 'selected' : ''); ?>>Draft</option>
                            <option value="active" <?php echo e(old('status') == 'active' ? 'selected' : ''); ?>>Active</option>
                            <option value="archived" <?php echo e(old('status') == 'archived' ? 'selected' : ''); ?>>Archived</option>
                        </select>
                    </div>

                    <!-- Product Organization -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
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
                                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($category->id); ?>" <?php echo e(old('category_id') == $category->id ? 'selected' : ''); ?>>
                                            <?php echo e($category->name); ?>

                                        </option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>

                            <div>
                                <label for="vendor" class="block text-sm font-medium text-gray-700 mb-2">
                                    Vendor
                                </label>
                                <input type="text" 
                                       id="vendor" 
                                       name="vendor" 
                                       value="<?php echo e(old('vendor')); ?>"
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
                                       value="<?php echo e(old('type')); ?>"
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
                                       value="<?php echo e(old('tags')); ?>"
                                       placeholder="e.g. summer, sale, new"
                                       class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="text-xs text-gray-500 mt-1">Separate tags with commas</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tax -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Tax</h2>
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   id="taxable" 
                                   name="taxable" 
                                   value="1"
                                   <?php echo e(old('taxable', true) ? 'checked' : ''); ?>

                                   class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                            <label for="taxable" class="ml-2 block text-sm text-gray-900">
                                Charge tax on this product
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="bg-white rounded-lg border border-gray-200 p-6">
                        <div class="space-y-3">
                            <button type="submit" 
                                    class="w-full px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition-colors">
                                Save product
                            </button>
                            <a href="<?php echo e(route('admin.products.index')); ?>" 
                               class="block w-full text-center px-4 py-2.5 bg-white text-gray-700 text-sm font-medium rounded-md border border-gray-300 hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        let uploadedImages = [];
        const imageInput = document.getElementById('imageInput');

        function handleImageUpload(event) {
            const files = Array.from(event.target.files);
            const previewGrid = document.getElementById('imagePreviewGrid');
            const uploadArea = document.getElementById('imageUploadArea');
            
            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        uploadedImages.push({
                            file: file,
                            preview: e.target.result
                        });
                        updateImagePreview();
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        function updateImagePreview() {
            const previewGrid = document.getElementById('imagePreviewGrid');
            const uploadArea = document.getElementById('imageUploadArea');
            
            if (uploadedImages.length > 0) {
                previewGrid.classList.remove('hidden');
                uploadArea.classList.add('hidden');
                
                previewGrid.innerHTML = uploadedImages.map((img, index) => `
                    <div class="relative group">
                        <img src="${img.preview}" alt="Preview" class="w-full h-32 object-cover rounded-lg border border-gray-200">
                        <button type="button" 
                                onclick="removeImage(${index})"
                                class="absolute top-2 right-2 p-1 bg-red-500 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        ${index === 0 ? '<div class="absolute bottom-2 left-2 px-2 py-1 bg-blue-500 text-white text-xs rounded">Primary</div>' : ''}
                    </div>
                `).join('');
            } else {
                previewGrid.classList.add('hidden');
                uploadArea.classList.remove('hidden');
            }
        }

        function removeImage(index) {
            uploadedImages.splice(index, 1);
            // Update the file input
            const dt = new DataTransfer();
            uploadedImages.forEach(img => dt.items.add(img.file));
            imageInput.files = dt.files;
            updateImagePreview();
        }

        // Allow drag and drop
        const uploadArea = document.getElementById('imageUploadArea');
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('border-indigo-400', 'bg-indigo-50');
        });

        uploadArea.addEventListener('dragleave', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-400', 'bg-indigo-50');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('border-indigo-400', 'bg-indigo-50');
            const files = Array.from(e.dataTransfer.files);
            const imageFiles = files.filter(f => f.type.startsWith('image/'));
            if (imageFiles.length > 0) {
                const dt = new DataTransfer();
                imageFiles.forEach(f => dt.items.add(f));
                imageInput.files = dt.files;
                handleImageUpload({ target: { files: imageInput.files } });
            }
        });

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
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale0f1cdd055772eb1d4a99981c240763e)): ?>
<?php $attributes = $__attributesOriginale0f1cdd055772eb1d4a99981c240763e; ?>
<?php unset($__attributesOriginale0f1cdd055772eb1d4a99981c240763e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale0f1cdd055772eb1d4a99981c240763e)): ?>
<?php $component = $__componentOriginale0f1cdd055772eb1d4a99981c240763e; ?>
<?php unset($__componentOriginale0f1cdd055772eb1d4a99981c240763e); ?>
<?php endif; ?>
<?php /**PATH C:\projects\tandil-backend\resources\views\admin\products\create.blade.php ENDPATH**/ ?>