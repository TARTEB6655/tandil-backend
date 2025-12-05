<x-admin-layout>
    <div class="space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-xl font-medium text-gray-900">Products</h1>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('admin.products.export', request()->query()) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    Export
                </a>
                <a href="{{ route('admin.products.import') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                    Import
                </a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                        More actions
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10 border border-gray-200">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Bulk edit</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Print barcodes</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Export selected</a>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.products.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-md hover:bg-gray-800 transition-colors">
                    Add product
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
                <p class="text-sm text-green-700">{{ session('success') }}</p>
            </div>
        @endif

        <!-- Filter Tabs -->
        <div class="bg-white rounded-lg border border-gray-200">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                <div class="flex items-center gap-1 overflow-x-auto">
                    <a href="{{ route('admin.products.index') }}" 
                       class="px-4 py-2 text-sm font-medium {{ !request('filter') ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        All
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'active']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ request('filter') == 'active' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Active
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'draft']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ request('filter') == 'draft' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Draft
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'archived']) }}" 
                       class="px-4 py-2 text-sm font-medium {{ request('filter') == 'archived' ? 'text-gray-900 border-b-2 border-gray-900' : 'text-gray-600 hover:text-gray-900' }} whitespace-nowrap">
                        Archived
                    </a>
                    <button class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-gray-600 whitespace-nowrap">
                        +
                    </button>
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Products Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" 
                                       id="selectAll" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                       onchange="toggleSelectAll()">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100">
                                <div class="flex items-center gap-1">
                                    Product
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                    </svg>
                                </div>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Inventory</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vendor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($products as $product)
                            <tr class="hover:bg-gray-50 transition-colors cursor-pointer" onclick="window.location='{{ route('admin.products.show', $product) }}'">
                                <td class="px-4 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                    <input type="checkbox" 
                                           class="product-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           value="{{ $product->id }}"
                                           onchange="updateBulkActions()">
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 flex-shrink-0 rounded border border-gray-200 overflow-hidden bg-gray-100">
                                            @if($product->image)
                                                <img src="{{ asset('storage/' . $product->image) }}" 
                                                     alt="{{ $product->name }}" 
                                                     class="h-full w-full object-cover">
                                            @else
                                                <div class="h-full w-full flex items-center justify-center">
                                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900">{{ $product->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ ($product->stock > 0 && !request('filter')) || request('filter') == 'active' ? 'bg-green-100 text-green-800' : 
                                           (request('filter') == 'draft' ? 'bg-gray-100 text-gray-800' : 
                                           (request('filter') == 'archived' ? 'bg-gray-100 text-gray-800' : 'bg-red-100 text-red-800')) }}">
                                        {{ ($product->stock > 0 && !request('filter')) || request('filter') == 'active' ? 'Active' : 
                                           (request('filter') == 'draft' ? 'Draft' : 
                                           (request('filter') == 'archived' ? 'Archived' : 'Out of stock')) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $product->stock ?? 0 }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->category->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $product->category->name ?? 'N/A' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">
                                    -
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.products.toggle-status', $product->id) }}" class="inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="text-xs px-2 py-1 rounded {{ $product->status === 'active' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }} transition-colors">
                                                {{ $product->status === 'active' ? 'Published' : 'Unpublished' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.products.show', $product->id) }}" 
                                           class="text-indigo-600 hover:text-indigo-900">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No products found</h3>
                                        <p class="text-sm text-gray-500 mb-4">Get started by creating a new product.</p>
                                        <a href="{{ route('admin.products.create') }}" 
                                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
                                            Add product
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        @if($products->hasPages())
            <div class="mt-4">
                {{ $products->links() }}
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            // Bulk actions logic here
        }
    </script>
    @endpush
</x-admin-layout>
