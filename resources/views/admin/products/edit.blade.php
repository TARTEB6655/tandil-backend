<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 mb-1">
                    <a href="{{ route('admin.products.index') }}" class="hover:text-gray-700 transition-colors">Products</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="text-gray-900 font-medium">Edit product</span>
                </nav>
                <h1 class="text-xl font-semibold text-gray-900">Edit Product</h1>
                <p class="mt-1 text-sm text-gray-500">Update product details and images</p>
            </div>
            <a href="{{ route('admin.products.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to products
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main content (2/3) -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Product details card -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50">
                            <h2 class="text-sm font-semibold text-gray-900">Product details</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Name, description, price and category</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input type="text" id="name" name="name" value="{{ old('name', $product->name) }}" required
                                       class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900">
                                @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea id="description" name="description" rows="4"
                                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900"
                                          placeholder="Describe your product...">{{ old('description', $product->description) }}</textarea>
                                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label for="price" class="block text-sm font-medium text-gray-700 mb-1">Price (AED) <span class="text-red-500">*</span></label>
                                    <input type="number" id="price" name="price" step="0.01" value="{{ old('price', $product->price) }}" required
                                           class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900">
                                    @error('price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                                    <select id="category_id" name="category_id"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-gray-900">
                                        <option value="">No category</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar (1/3) -->
                <div class="space-y-6">
                    <!-- Media card -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50">
                            <h2 class="text-sm font-semibold text-gray-900">Media</h2>
                            <p class="text-xs text-gray-500 mt-0.5">Current images and upload new ones</p>
                        </div>
                        <div class="p-6 space-y-4">
                            @if($product->images->isNotEmpty())
                                <div>
                                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Current images</p>
                                    <div class="flex flex-wrap gap-3">
                                        @foreach($product->images as $img)
                                            <div class="relative group">
                                                <img src="{{ $img->getImageUrl() }}" alt="Product" class="h-20 w-20 object-cover rounded-lg border border-gray-200 shadow-sm">
                                                @if($img->is_primary)
                                                    <span class="absolute bottom-0 left-0 right-0 bg-indigo-600 text-white text-xs text-center py-0.5 rounded-b-lg font-medium">Primary</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">New image (single)</label>
                                <p class="text-xs text-gray-500 mb-2">Leave blank to keep current</p>
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/jpg"
                                       class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Or add more images (multiple)</label>
                                <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/jpg"
                                       class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                                @error('images.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Actions card -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="p-6 flex flex-col gap-3">
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 shadow-sm transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Update product
                            </button>
                            <a href="{{ route('admin.products.index') }}"
                               class="w-full inline-flex justify-center items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-admin-layout>
