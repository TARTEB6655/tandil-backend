<x-vendor-layout>
    <x-dashboard.page-header title="Inventory" subtitle="Update stock levels and low-stock thresholds." />

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search..." class="rounded-lg border-gray-300 text-sm" />
        <select name="filter" class="rounded-lg border-gray-300 text-sm">
            <option value="">All</option>
            <option value="low" @selected(request('filter') === 'low')>Low stock</option>
            <option value="out" @selected(request('filter') === 'out')>Out of stock</option>
        </select>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="space-y-4">
        @forelse($items as $vp)
            @php
                $qty = $vp->inventory?->quantity ?? 0;
                $threshold = $vp->inventory?->low_stock_threshold ?? 5;
                $isLow = $qty <= $threshold;
                $isOut = $qty <= 0;
            @endphp
            <form method="POST" action="{{ route('vendor.inventory.update', $vp->id) }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm {{ $isOut ? 'border-red-200' : ($isLow ? 'border-amber-200' : '') }}">
                @csrf
                @method('PUT')
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-gray-900">{{ $vp->product?->name }}</p>
                        <p class="text-xs text-gray-500">SKU: {{ $vp->product?->sku ?? '—' }}</p>
                        @if($isOut)
                            <span class="mt-1 inline-block rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">Out of stock</span>
                        @elseif($isLow)
                            <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Low stock</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-end gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Quantity</label>
                            <input type="number" name="quantity" min="0" value="{{ $qty }}" class="mt-1 w-24 rounded-lg border-gray-300 text-sm" required />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Alert at</label>
                            <input type="number" name="low_stock_threshold" min="0" value="{{ $threshold }}" class="mt-1 w-24 rounded-lg border-gray-300 text-sm" />
                        </div>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save</button>
                    </div>
                </div>
            </form>
        @empty
            <p class="rounded-xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">No products in inventory.</p>
        @endforelse
    </div>

    @if($items->hasPages())
        <div class="mt-4">{{ $items->links() }}</div>
    @endif
</x-vendor-layout>
