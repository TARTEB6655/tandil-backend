<x-admin-layout>
    <x-admin.vendor.shell
        x-data="{
            selected: [],
            pageIds: {{ $products->pluck('id')->values()->toJson() }},
            bulkOpen: false,
            toggleAll(checked) { this.selected = checked ? [...this.pageIds] : []; this.bulkOpen = this.selected.length > 0; },
            toggleOne(id, checked) {
                id = Number(id);
                if (checked) { if (!this.selected.includes(id)) this.selected.push(id); }
                else { this.selected = this.selected.filter(i => i !== id); }
                this.bulkOpen = this.selected.length > 0;
            },
            isSelected(id) { return this.selected.includes(Number(id)); },
            allSelected() { return this.pageIds.length > 0 && this.selected.length === this.pageIds.length; },
            clearSelection() { this.selected = []; this.bulkOpen = false; }
        }">

        <x-admin.vendor.nav :vendor="$vendor" />
        <x-admin.vendor.flash />

        <x-admin.vendor.page-header
            :title="'Products — '.$vendor->profile?->business_name"
            description="Full catalog control — enable or disable products on the marketplace with the Live toggle.">
            <x-slot:actions>
                <x-admin.vendor.btn variant="secondary" type="button" @click="bulkOpen = !bulkOpen">Bulk actions</x-admin.vendor.btn>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
            <x-admin.vendor.stat-card label="Total Products" :value="number_format($stats['total'] ?? 0)" />
            <x-admin.vendor.stat-card label="Live" :value="number_format($stats['active'] ?? 0)" accent="text-emerald-600" />
            <x-admin.vendor.stat-card label="Disabled" :value="number_format($stats['disabled'] ?? 0)" accent="text-rose-600" />
            <x-admin.vendor.stat-card label="Draft" :value="number_format($stats['draft'] ?? 0)" />
            <x-admin.vendor.stat-card label="Out of Stock" :value="number_format($stats['out_of_stock'] ?? 0)" accent="text-sky-600" />
        </div>

        <div x-show="selected.length > 0" x-cloak class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-indigo-200 bg-indigo-50/80 px-4 py-3 dark:border-indigo-900/50 dark:bg-indigo-950/30">
            <p class="text-sm font-medium text-indigo-900 dark:text-indigo-200"><span x-text="selected.length"></span> product(s) selected</p>
            <x-admin.vendor.btn variant="ghost" type="button" @click="clearSelection()">Clear</x-admin.vendor.btn>
        </div>

        <div x-show="bulkOpen" x-cloak>
            <x-admin.vendor.card title="Bulk product actions">
                <form method="POST" action="{{ route('admin.vendors.products.bulk', $vendor) }}" class="flex flex-wrap items-end gap-4" onsubmit="return confirm('Apply this bulk action to selected products?')">
                    @csrf
                    <template x-for="id in selected" :key="id"><input type="hidden" name="product_ids[]" :value="id" /></template>
                    <div class="min-w-[180px]">
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">Action</label>
                        <select name="action" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" required>
                            <option value="enable">Enable selected</option>
                            <option value="disable">Disable selected</option>
                            <option value="delete">Delete selected</option>
                        </select>
                    </div>
                    <div class="min-w-[220px]">
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">Reason (optional, for disable)</label>
                        <input name="reason" placeholder="Optional disable reason" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    </div>
                    <x-admin.vendor.btn variant="primary" type="submit" x-bind:disabled="selected.length === 0">Apply to <span x-text="selected.length"></span> product(s)</x-admin.vendor.btn>
                </form>
            </x-admin.vendor.card>
        </div>

        <x-admin.vendor.card :padding="false">
            <form method="GET" class="grid gap-4 border-b border-gray-100 p-4 dark:border-gray-800 lg:grid-cols-6">
                <div class="relative lg:col-span-2">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="search" value="{{ request('search') }}" placeholder="Search name or SKU..." class="w-full rounded-md border-gray-300 py-2 pl-10 text-sm dark:border-gray-700 dark:bg-gray-900" />
                </div>
                <select name="category_id" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)<option value="{{ $cat->id }}" @selected(request('category_id')==$cat->id)>{{ $cat->name }}</option>@endforeach
                </select>
                <select name="sort" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <option value="newest" @selected(request('sort','newest')==='newest')>Newest</option>
                    <option value="oldest" @selected(request('sort')==='oldest')>Oldest</option>
                    <option value="name" @selected(request('sort')==='name')>Name</option>
                    <option value="price_high" @selected(request('sort')==='price_high')>Price high</option>
                    <option value="price_low" @selected(request('sort')==='price_low')>Price low</option>
                    <option value="stock_low" @selected(request('sort')==='stock_low')>Stock low</option>
                </select>
                <div class="flex gap-2 lg:col-span-6">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                    <x-admin.vendor.btn variant="primary" type="submit">Apply filters</x-admin.vendor.btn>
                </div>
            </form>

            <div class="max-h-[calc(100vh-14rem)] overflow-auto">
                <table class="min-w-[1400px] w-full text-sm">
                    <thead class="sticky top-0 z-10 border-b border-gray-200 bg-gray-50/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
                        <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <th class="w-12 px-4 py-3"><input type="checkbox" class="rounded border-gray-300" :checked="allSelected()" @change="toggleAll($event.target.checked)" /></th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">SKU</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3 text-right">Sales</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Updated</th>
                            <th class="px-4 py-3 text-center">Live</th>
                            <th class="w-12 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($products as $vp)
                            @php
                                $stock = $vp->inventory?->quantity ?? 0;
                                $sales = $salesMap[$vp->product_id] ?? 0;
                                $price = $vp->currentPrice?->price ?? $vp->product?->price ?? 0;
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40" :class="isSelected({{ $vp->id }}) ? 'bg-indigo-50/50' : ''">
                                <td class="px-4 py-4"><input type="checkbox" class="rounded border-gray-300" :checked="isSelected({{ $vp->id }})" @change="toggleOne({{ $vp->id }}, $event.target.checked)" /></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($vp->product?->image_url)
                                            <img src="{{ $vp->product->image_url }}" alt="" class="h-10 w-10 rounded-md border object-cover dark:border-gray-700" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100 text-xs text-gray-500 dark:bg-gray-800">—</div>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.vendors.products.show', [$vendor, $vp]) }}" class="truncate font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100">{{ $vp->product?->name }}</a>
                                            @if($vp->product?->is_featured)<span class="ml-1 text-[10px] font-medium text-amber-600">Featured</span>@endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-gray-600">{{ $vp->product?->sku ?? '—' }}</td>
                                <td class="px-4 py-4 text-gray-600">{{ $vp->product?->category?->name ?? '—' }}</td>
                                <td class="px-4 py-4 text-right tabular-nums font-medium">AED {{ number_format($price, 2) }}</td>
                                <td class="px-4 py-4 text-right tabular-nums">{{ $stock }}</td>
                                <td class="px-4 py-4 text-right tabular-nums text-gray-600">{{ number_format($sales) }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500">{{ $vp->created_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-4 text-xs text-gray-500">{{ $vp->updated_at?->diffForHumans() }}</td>
                                <td class="px-4 py-4 text-center">
                                    <form method="POST" action="{{ route($vp->isMarketplaceVisible() ? 'admin.vendors.products.disable' : 'admin.vendors.products.enable', [$vendor, $vp]) }}" onsubmit="return confirm('{{ $vp->isMarketplaceVisible() ? 'Disable this product on the marketplace?' : 'Enable this product on the marketplace?' }}')">
                                        @csrf
                                        <button type="submit" @class([
                                            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors',
                                            'bg-indigo-600' => $vp->isMarketplaceVisible(),
                                            'bg-gray-200 dark:bg-gray-700' => ! $vp->isMarketplaceVisible(),
                                        ])>
                                            <span @class([
                                                'inline-block h-5 w-5 transform rounded-full bg-white shadow transition',
                                                'translate-x-5' => $vp->isMarketplaceVisible(),
                                                'translate-x-0.5' => ! $vp->isMarketplaceVisible(),
                                            ])></span>
                                        </button>
                                    </form>
                                </td>
                                <td class="px-4 py-4">@include('admin.vendors.partials.product-actions', ['vendor' => $vendor, 'vp' => $vp])</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="p-0"><x-admin.vendor.empty-state title="No products found" description="This vendor has no products matching your filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($products->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $products->links() }}</div>
            @endif
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
