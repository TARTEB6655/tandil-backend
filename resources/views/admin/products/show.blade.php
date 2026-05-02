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
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Product details</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Product details</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View product information and all media</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.products.edit', $product) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">Edit product</a>
                <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to list
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            @if(session('success'))
                <div class="mx-5 mt-5 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 flex items-center gap-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            @endif

            <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Media: main image + all thumbnails -->
                <div>
                    <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Media ({{ $product->images->count() }} images)</h2>
                    @php
                        $allImages = $product->images->sortBy('sort_order')->values();
                        $uniqueImages = collect(\App\Models\ProductImage::uniqueByPath($allImages))->sortBy('sort_order')->values();
                        $primaryImg = $uniqueImages->firstWhere('is_primary', true) ?? $uniqueImages->first();
                    @endphp
                    @if($uniqueImages->isNotEmpty())
                        <div class="w-full aspect-square max-w-md rounded-xl border border-gray-200 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700 mb-4">
                            <img src="{{ $primaryImg ? $primaryImg->getImageUrl() : '' }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">All images (scroll to see all)</p>
                        <div class="flex items-center gap-2 overflow-x-auto pb-2" style="scrollbar-width: thin;">
                            @foreach($uniqueImages as $img)
                                <div class="relative flex-shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 {{ $img->is_primary ? 'border-indigo-500' : 'border-gray-200 dark:border-gray-600' }}">
                                    <img src="{{ $img->getImageUrl() }}" alt="" class="w-full h-full object-cover">
                                    @if($img->is_primary)<span class="absolute bottom-0 left-0 right-0 bg-indigo-600 text-white text-[10px] font-medium text-center py-0.5">Main</span>@endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="w-full aspect-square max-w-md rounded-xl border border-dashed border-gray-200 dark:border-gray-600 flex items-center justify-center bg-gray-50 dark:bg-gray-700/30 text-gray-500 dark:text-gray-400 text-sm">No images</div>
                    @endif
                </div>

                <!-- Info -->
                <div class="space-y-4">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</h2>
                    <p class="text-xl font-semibold text-indigo-600 dark:text-indigo-400">AED {{ number_format($product->price, 2) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Category: {{ $product->category->name ?? 'No category' }}</p>
                    @if($product->description)
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-wrap">{{ $product->description }}</p>
                        </div>
                    @endif
                    <dl class="grid grid-cols-2 gap-2 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">SKU</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->sku ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Handle</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->handle ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->status ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Stock</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->stock ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Estimated arrival</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->estimated_arrival ?? '—' }}</dd>
                        <dt class="text-gray-500 dark:text-gray-400">Job duration</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ $product->job_duration ?? '—' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex gap-3">
                <a href="{{ route('admin.products.edit', $product) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors">Edit product</a>
                <a href="{{ route('admin.products.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">Back to products</a>
            </div>
        </div>
    </div>
</x-admin-layout>
