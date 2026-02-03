<x-admin-layout>
    <div class="space-y-6">
        <!-- Breadcrumb & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <nav class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-1">
                    <a href="{{ route('admin.dashboard') }}" class="hover:text-gray-700 dark:hover:text-gray-300 transition-colors">Dashboard</a>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    <span class="text-gray-900 dark:text-gray-100 font-medium">Products</span>
                </nav>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Products</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your product catalog</p>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <a href="{{ route('admin.products.export', request()->query()) }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Export
                </a>
                <a href="{{ route('admin.products.import') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                    Import
                </a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" 
                            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        More actions
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10 border border-gray-200 dark:border-gray-600">
                        <div class="py-1">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Bulk edit</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Print barcodes</a>
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Export selected</a>
                        </div>
                    </div>
                </div>
                <a href="{{ route('admin.products.create') }}" 
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    Add product
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 flex items-center gap-3 shadow-sm">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-green-100 dark:bg-green-800/50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
            </div>
        @endif

        <!-- Bulk Actions Bar -->
        <div id="bulkActionsBar" class="hidden bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-xl p-4 mb-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-indigo-900 dark:text-indigo-200">
                        <span id="selectedCount">0</span> product(s) selected
                    </span>
                    <div class="flex items-center gap-2">
                        <button onclick="bulkEdit()" 
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-indigo-700 dark:text-indigo-300 bg-white dark:bg-gray-800 border border-indigo-300 dark:border-indigo-600 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" 
                                    class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-indigo-700 bg-white border border-indigo-300 rounded-md hover:bg-indigo-50 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                                </svg>
                                Change Status
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak
                                 class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg z-10 border border-gray-200 dark:border-gray-600">
                                <div class="py-1">
                                    <button onclick="bulkChangeStatus('active')" class="w-full text-left block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Set to Active</button>
                                    <button onclick="bulkChangeStatus('draft')" class="w-full text-left block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Set to Draft</button>
                                    <button onclick="bulkChangeStatus('archived')" class="w-full text-left block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Set to Archived</button>
                                </div>
                            </div>
                        </div>
                        <button onclick="bulkDelete()" 
                                class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-300 bg-white dark:bg-gray-800 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
                <button onclick="clearSelection()" 
                        class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium">
                    Clear selection
                </button>
            </div>
        </div>

        <!-- Filter Tabs & Table Card -->
        <div class="bg-white dark:bg-white/5 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
                <div class="flex items-center gap-1 overflow-x-auto">
                    <a href="{{ route('admin.products.index') }}" 
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap {{ !request('filter') ? 'text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800/50' }}">
                        All
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'active']) }}" 
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap {{ request('filter') == 'active' ? 'text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800/50' }}">
                        Active
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'draft']) }}" 
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap {{ request('filter') == 'draft' ? 'text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800/50' }}">
                        Draft
                    </a>
                    <a href="{{ route('admin.products.index', ['filter' => 'archived']) }}" 
                       class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap {{ request('filter') == 'archived' ? 'text-indigo-600 dark:text-indigo-400 bg-white dark:bg-gray-800 border-b-2 border-indigo-600 dark:border-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800/50' }}">
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
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700" style="min-width: 1100px;">
                    <thead class="bg-gray-50 dark:bg-gray-800/80">
                        <tr>
                            <th class="px-4 py-3.5 text-left">
                                <input type="checkbox" 
                                       id="selectAll" 
                                       class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-600 rounded"
                                       onchange="toggleSelectAll()">
                            </th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                                Product
                            </th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Inventory</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Price</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider w-32">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900/50 divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($products as $product)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors cursor-pointer" onclick="window.location='{{ route('admin.products.show', $product) }}'">
                                <td class="px-4 py-4 whitespace-nowrap" onclick="event.stopPropagation()">
                                    <input type="checkbox" 
                                           class="product-checkbox h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                           value="{{ $product->id }}"
                                           onchange="updateBulkActions()">
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3 min-w-[250px]">
                                        <div class="h-16 w-16 flex-shrink-0 rounded-lg border-2 border-gray-200 overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 shadow-sm">
                                            @php
                                                $imageUrl = $product->getImageUrl();
                                            @endphp
                                            @if($imageUrl)
                                                <img src="{{ $imageUrl }}" 
                                                     alt="{{ $product->name }}" 
                                                     class="h-full w-full object-cover"
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'h-full w-full flex items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100\'><svg class=\'w-8 h-8 text-indigo-400\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\' /></svg></div>';">
                                            @else
                                                <div class="h-full w-full flex items-center justify-center bg-gradient-to-br from-indigo-100 to-purple-100">
                                                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">{{ $product->name }}</div>
                                            @if($product->sku)
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">SKU: {{ $product->sku }}</div>
                                            @endif
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
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-400">
                                    {{ $product->category->name ?? '—' }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format($product->price ?? 0, 2) }} AED
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
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="flex flex-col items-center max-w-sm mx-auto">
                                        <div class="w-16 h-16 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center mb-4">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                            </svg>
                                        </div>
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">No products yet</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">Add your first product to start selling.</p>
                                        <a href="{{ route('admin.products.create') }}" 
                                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-800 dark:hover:bg-gray-100 transition-colors shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
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
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                bulkBar.classList.remove('hidden');
                selectedCount.textContent = checkboxes.length;
            } else {
                bulkBar.classList.add('hidden');
            }
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            const selectAll = document.getElementById('selectAll');
            checkboxes.forEach(cb => cb.checked = false);
            selectAll.checked = false;
            updateBulkActions();
        }
        
        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.product-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        function bulkEdit() {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('Please select at least one product');
                return;
            }
            if (ids.length === 1) {
                window.location.href = `/admin/products/${ids[0]}/edit`;
            } else {
                alert('Bulk edit for multiple products - redirecting to first product');
                window.location.href = `/admin/products/${ids[0]}/edit`;
            }
        }
        
        function bulkChangeStatus(status) {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('Please select at least one product');
                return;
            }
            
            if (!confirm(`Are you sure you want to change status of ${ids.length} product(s) to ${status}?`)) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.products.bulk-update-status") }}';
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkDelete() {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('Please select at least one product');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${ids.length} product(s)? This action cannot be undone.`)) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("admin.products.bulk-delete") }}';
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'product_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
    @endpush
</x-admin-layout>
