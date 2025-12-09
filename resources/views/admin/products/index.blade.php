@php
    use Illuminate\Support\Facades\Storage;
@endphp
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
                <div class="flex items-center gap-1 overflow-x-visible">
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
                </div>
                <div class="flex items-center gap-2 ml-4">
                    <!-- View Toggle -->
                    <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-1">
                        <button onclick="showTableView()" id="tableViewBtn" class="p-2 text-gray-900 rounded-md hover:bg-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                        <button onclick="showCardView()" id="cardViewBtn" class="p-2 text-gray-600 rounded-md hover:bg-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </button>
                    </div>
                    <button class="p-2 text-gray-600 hover:text-gray-900 rounded-md hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Products Card View -->
            <div id="cardView" class="hidden p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @forelse($products as $product)
                        <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-lg transition-all duration-300 overflow-hidden group">
                            <!-- Product Image -->
                            <div class="relative h-48 bg-gray-100 overflow-hidden">
                                @if($product->image)
                                    <img src="{{ Storage::disk('public')->exists($product->image) ? asset('storage/' . $product->image) : (str_starts_with($product->image, 'http') ? $product->image : asset('images/placeholder.png')) }}" 
                                         alt="{{ $product->name }}" 
                                         class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                                <!-- Status Badge -->
                                <div class="absolute top-3 left-3">
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full 
                                        {{ ($product->stock > 0 && !request('filter')) || request('filter') == 'active' ? 'bg-green-500 text-white' : 
                                           (request('filter') == 'draft' ? 'bg-gray-500 text-white' : 
                                           (request('filter') == 'archived' ? 'bg-gray-400 text-white' : 'bg-red-500 text-white')) }}">
                                        {{ ($product->stock > 0 && !request('filter')) || request('filter') == 'active' ? 'Active' : 
                                           (request('filter') == 'draft' ? 'Draft' : 
                                           (request('filter') == 'archived' ? 'Archived' : 'Out of Stock')) }}
                                    </span>
                                </div>
                                <!-- Quick Actions Overlay -->
                                <div class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <div class="flex gap-2">
                                        <a href="{{ route('admin.products.edit', $product->id) }}" 
                                           class="p-2 bg-white rounded-lg shadow-md hover:bg-gray-50 transition-colors"
                                           title="Edit">
                                            <svg class="w-4 h-4 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" 
                                              class="inline"
                                              onsubmit="return confirm('Are you sure?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="p-2 bg-white rounded-lg shadow-md hover:bg-red-50 transition-colors"
                                                    title="Delete">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Product Info -->
                            <div class="p-4">
                                <div class="mb-2">
                                    <h3 class="text-base font-semibold text-gray-900 line-clamp-1 mb-1">{{ $product->name }}</h3>
                                    @if($product->category)
                                        <p class="text-xs text-gray-500">{{ $product->category->name }}</p>
                                    @endif
                                </div>
                                
                                <!-- Price -->
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-lg font-bold text-gray-900">AED {{ number_format($product->price, 2) }}</span>
                                    @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                        <span class="text-sm text-gray-500 line-through">AED {{ number_format($product->compare_at_price, 2) }}</span>
                                        <span class="text-xs font-medium text-red-600 bg-red-50 px-2 py-0.5 rounded">
                                            {{ round((($product->compare_at_price - $product->price) / $product->compare_at_price) * 100) }}% OFF
                                        </span>
                                    @endif
                                </div>
                                
                                <!-- Stock & SKU -->
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
                                    <span>Stock: <strong class="text-gray-900">{{ $product->stock ?? 0 }}</strong></span>
                                    @if($product->sku)
                                        <span>SKU: <strong class="text-gray-900">{{ $product->sku }}</strong></span>
                                    @endif
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center gap-2 pt-3 border-t border-gray-100">
                                    <a href="{{ route('admin.products.show', $product->id) }}" 
                                       class="flex-1 text-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                                        View
                                    </a>
                                    <a href="{{ route('admin.products.edit', $product->id) }}" 
                                       class="flex-1 text-center px-3 py-2 text-sm font-medium text-white bg-gray-900 rounded-lg hover:bg-gray-800 transition-colors">
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-span-full text-center py-12">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No products found</h3>
                            <p class="text-sm text-gray-500 mb-4">Get started by creating a new product.</p>
                            <a href="{{ route('admin.products.create') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800">
                                Add product
                            </a>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Products Table View -->
            <div id="tableView" class="overflow-x-visible">
                <table class="w-full divide-y divide-gray-200">
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
                                                <img src="{{ Storage::disk('public')->exists($product->image) ? asset('storage/' . $product->image) : (str_starts_with($product->image, 'http') ? $product->image : asset('images/placeholder.png')) }}" 
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
                                        <a href="{{ route('admin.products.edit', $product->id) }}" 
                                           class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <form method="POST" action="{{ route('admin.products.destroy', $product->id) }}" 
                                              class="inline"
                                              onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="text-red-600 hover:text-red-900" title="Delete">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.products.show', $product->id) }}" 
                                           class="text-indigo-600 hover:text-indigo-900" title="View">
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
        @if($products->total() > 0)
            <div class="mt-6 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ $products->firstItem() }} to {{ $products->lastItem() }} of {{ $products->total() }} results
                </div>
                <div>
                    {{ $products->links() }}
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        // View Toggle Functions
        function showTableView() {
            document.getElementById('tableView').classList.remove('hidden');
            document.getElementById('cardView').classList.add('hidden');
            document.getElementById('tableViewBtn').classList.add('text-gray-900', 'bg-white');
            document.getElementById('tableViewBtn').classList.remove('text-gray-600');
            document.getElementById('cardViewBtn').classList.remove('text-gray-900', 'bg-white');
            document.getElementById('cardViewBtn').classList.add('text-gray-600');
        }
        
        function showCardView() {
            document.getElementById('cardView').classList.remove('hidden');
            document.getElementById('tableView').classList.add('hidden');
            document.getElementById('cardViewBtn').classList.add('text-gray-900', 'bg-white');
            document.getElementById('cardViewBtn').classList.remove('text-gray-600');
            document.getElementById('tableViewBtn').classList.remove('text-gray-900', 'bg-white');
            document.getElementById('tableViewBtn').classList.add('text-gray-600');
        }
        
        // Initialize with table view
        document.addEventListener('DOMContentLoaded', function() {
            showTableView();
        });
        
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
