<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Dashboard</a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <a href="{{ route('admin.services.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Services</a>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
            <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $category->name }}</span>
        </nav>

        <!-- Category header card -->
        @php
            $iconMap = ['water' => '💧', 'leaf' => '🌿', 'broom' => '🧹', 'heart' => '❤️', 'wrench' => '🔧'];
            $icon = $category->icon && isset($iconMap[$category->icon]) ? $iconMap[$category->icon] : '📋';
        @endphp
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="p-6 sm:p-8 flex flex-col sm:flex-row sm:items-center gap-6">
                <div class="flex-shrink-0 w-20 h-20 rounded-2xl bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-4xl">
                    {{ $icon }}
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $category->name }}</h1>
                    @if($category->description)
                        <p class="mt-2 text-gray-500 dark:text-gray-400">{{ $category->description }}</p>
                    @endif
                    <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $category->products_count }}</span> {{ Str::plural('product', $category->products_count) }} in this category
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('admin.categories.edit', $category->id) }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        Edit category
                    </a>
                    <a href="{{ route('admin.products.create') }}?category_id={{ $category->id }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        Add product
                    </a>
                </div>
            </div>
        </div>

        <!-- Products in this category -->
        <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Products in this category</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Services customers can order under {{ $category->name }}</p>
            </div>

            @if($category->products->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-1">No products yet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Add products and assign them to this category.</p>
                    <a href="{{ route('admin.products.create') }}?category_id={{ $category->id }}" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100">
                        Add product
                    </a>
                </div>
            @else
                <!-- Product cards grid -->
                <div class="p-4 sm:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach($category->products as $product)
                            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 p-4 hover:border-gray-300 dark:hover:border-gray-600 transition-colors flex flex-col">
                                <div class="flex items-start gap-4">
                                    @if($product->image_url ?? null)
                                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-14 h-14 rounded-lg object-cover border border-gray-200 dark:border-gray-600 flex-shrink-0" loading="lazy">
                                    @else
                                        <div class="w-14 h-14 rounded-lg bg-gray-200 dark:bg-gray-600 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-7 h-7 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4z" />
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <h3 class="font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</h3>
                                        @if($product->description)
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ Str::limit($product->description, 60) }}</p>
                                        @endif
                                        <div class="mt-2 flex items-center justify-between gap-2 flex-wrap">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format($product->price ?? 0, 2) }}</span>
                                            @if(($product->status ?? 'draft') === 'active')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300">Active</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">{{ $product->status ?? 'draft' }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-600">
                                    <a href="{{ route('admin.products.edit', $product->id) }}" 
                                       class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        Edit product
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex justify-start">
            <a href="{{ route('admin.services.index') }}" 
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Services
            </a>
        </div>
    </div>
</x-admin-layout>
