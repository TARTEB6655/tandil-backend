<x-admin-layout>
    <x-admin.vendor.shell x-data="{ view: 'table' }">
        <x-admin.vendor.nav :vendor="$vendor" />

        <x-admin.vendor.flash />

        <x-admin.vendor.page-header
            :title="'Products — '.$vendor->profile?->business_name"
            description="Manage catalog visibility, approvals, and inventory for this vendor.">
            <x-slot:actions>
                <div class="inline-flex rounded-md border border-gray-300 p-0.5 dark:border-gray-700">
                    <button type="button" @click="view = 'table'"
                            :class="view === 'table' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400'"
                            class="rounded px-3 py-1.5 text-sm font-medium transition-colors">
                        Table
                    </button>
                    <button type="button" @click="view = 'grid'"
                            :class="view === 'grid' ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400'"
                            class="rounded px-3 py-1.5 text-sm font-medium transition-colors">
                        Grid
                    </button>
                </div>
            </x-slot:actions>
        </x-admin.vendor.page-header>

        <x-admin.vendor.card :padding="false">
            <form method="GET" class="flex flex-col gap-4 border-b border-gray-100 p-4 dark:border-gray-800 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input name="search" value="{{ request('search') }}" placeholder="Search products..."
                           class="w-full rounded-md border-gray-300 py-2 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-900" />
                </div>
                <select name="approval_status" class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:w-40">
                    <option value="">Approval</option>
                    @foreach(['pending','approved','rejected'] as $s)
                        <option value="{{ $s }}" @selected(request('approval_status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:w-40">
                    <option value="">Status</option>
                    @foreach(['active','inactive','draft'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <x-admin.vendor.btn variant="primary" type="submit">Filter</x-admin.vendor.btn>
            </form>

            {{-- Table view --}}
            <div x-show="view === 'table'" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50/80 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Approval</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($products as $vp)
                            @php
                                $stock = $vp->inventory?->quantity ?? 0;
                                $lowThreshold = $vp->inventory?->low_stock_threshold ?? 5;
                                $stockState = $stock <= 0 ? 'out' : ($stock <= $lowThreshold ? 'low' : 'ok');
                            @endphp
                            <tr class="transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($vp->product?->image_url)
                                            <img src="{{ $vp->product->image_url }}" alt="" class="h-10 w-10 rounded-md border border-gray-200 object-cover dark:border-gray-700" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-gray-100 text-xs font-medium text-gray-500 dark:bg-gray-800">—</div>
                                        @endif
                                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $vp->product?->name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-gray-600 dark:text-gray-300">{{ $vp->product?->category?->name ?? '—' }}</td>
                                <td class="px-4 py-4"><x-admin.vendor.status-badge :status="$vp->approval_status" /></td>
                                <td class="px-4 py-4"><x-admin.vendor.status-badge :status="$vp->status" /></td>
                                <td class="px-4 py-4 text-right tabular-nums font-medium">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</td>
                                <td class="px-4 py-4 text-right">
                                    <span @class([
                                        'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium',
                                        'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20' => $stockState === 'out',
                                        'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20' => $stockState === 'low',
                                        'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' => $stockState === 'ok',
                                    ])>{{ $stock }} in stock</span>
                                </td>
                                <td class="px-4 py-4">
                                    @include('admin.vendors.partials.product-actions', ['vendor' => $vendor, 'vp' => $vp])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-0">
                                    <x-admin.vendor.empty-state title="No products found" description="This vendor has no products matching your filters." />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Grid view --}}
            <div x-show="view === 'grid'" x-cloak class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($products as $vp)
                    @php
                        $stock = $vp->inventory?->quantity ?? 0;
                        $lowThreshold = $vp->inventory?->low_stock_threshold ?? 5;
                        $stockState = $stock <= 0 ? 'out' : ($stock <= $lowThreshold ? 'low' : 'ok');
                    @endphp
                    <div class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                        <div class="aspect-square bg-gray-50 dark:bg-gray-800/50">
                            @if($vp->product?->image_url)
                                <img src="{{ $vp->product->image_url }}" alt="" class="h-full w-full object-cover" />
                            @else
                                <div class="flex h-full items-center justify-center text-sm text-gray-400">No image</div>
                            @endif
                        </div>
                        <div class="space-y-3 p-4">
                            <div>
                                <h3 class="truncate font-medium text-gray-900 dark:text-gray-100">{{ $vp->product?->name }}</h3>
                                <p class="text-xs text-gray-500">{{ $vp->product?->category?->name ?? 'Uncategorized' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <x-admin.vendor.status-badge :status="$vp->approval_status" />
                                <x-admin.vendor.status-badge :status="$vp->status" />
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</span>
                                <span @class([
                                    'text-xs font-medium',
                                    'text-rose-600' => $stockState === 'out',
                                    'text-amber-600' => $stockState === 'low',
                                    'text-emerald-600' => $stockState === 'ok',
                                ])>{{ $stock }} in stock</span>
                            </div>
                            <div class="flex justify-end border-t border-gray-100 pt-3 dark:border-gray-800">
                                @include('admin.vendors.partials.product-actions', ['vendor' => $vendor, 'vp' => $vp])
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full">
                        <x-admin.vendor.empty-state title="No products found" description="This vendor has no products matching your filters." />
                    </div>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">{{ $products->links() }}</div>
            @endif
        </x-admin.vendor.card>
    </x-admin.vendor.shell>
</x-admin-layout>
