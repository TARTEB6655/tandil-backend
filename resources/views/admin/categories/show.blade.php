<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">{{ __('admin.dashboard') }}</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <a href="{{ route('admin.categories.index') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">{{ __('admin.categories') }}</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $category->name }}</span>
                </nav>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">{{ $category->name }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $products->total() }} product(s) in this category</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.categories.edit', $category->id) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors">
                    Edit Category
                </a>
                <a href="{{ route('admin.categories.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Back
                </a>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">{{ __('admin.image') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('admin.name') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">{{ __('admin.status') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">{{ __('admin.inventory') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">{{ __('admin.price') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                        @forelse($products as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors cursor-pointer"
                                onclick="window.location='{{ route('admin.products.show', $product->id) }}'">
                                <td class="px-4 py-3">
                                    @php $imageUrl = $product->getImageUrl(); @endphp
                                    @if($imageUrl)
                                        <img src="{{ $imageUrl }}" alt="{{ $product->name }}" class="w-12 h-12 rounded-lg object-cover border border-gray-200 shadow-sm">
                                    @else
                                        <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center border border-gray-200">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6 6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $product->name }}</div>
                                    @if($product->sku)
                                        <div class="text-xs text-gray-500 mt-0.5">SKU: {{ $product->sku }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $product->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ ucfirst($product->status ?? 'draft') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $product->stock ?? 0 }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ number_format($product->price ?? 0, 2) }} AED</td>
                                <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                                    <a href="{{ route('admin.products.edit', $product->id) }}" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:bg-indigo-50 rounded-md transition-colors">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <p class="text-sm text-gray-500">No products in this category yet.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($products->hasPages())
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>
</x-admin-layout>

