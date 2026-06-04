<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold">Inventory monitoring</h1>
        <x-admin.marketplace-nav />
        <div class="flex flex-wrap gap-4 text-sm">
            <span>Low stock: <strong>{{ $stats['low_stock'] }}</strong></span>
            <span>Out of stock: <strong>{{ $stats['out_of_stock'] }}</strong></span>
            <span>Tracked SKUs: <strong>{{ $stats['total_tracked'] }}</strong></span>
        </div>
        <div class="flex gap-2">
            @foreach(['all' => 'All', 'low' => 'Low stock', 'out' => 'Out of stock', 'inconsistent' => 'Inconsistent'] as $key => $label)
                <a href="{{ route('admin.marketplace.inventory.index', ['filter' => $key]) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $filter === $key ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">Vendor</th><th class="px-4 py-3 text-left">Qty</th><th class="px-4 py-3 text-left">Threshold</th><th class="px-4 py-3 text-left">Product.stock</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($items as $inv)
                        <tr>
                            <td class="px-4 py-3">{{ $inv->vendorProduct?->product?->name }}</td>
                            <td class="px-4 py-3">{{ $inv->vendorProduct?->vendor?->profile?->business_name }}</td>
                            <td class="px-4 py-3 font-medium {{ $inv->quantity <= 0 ? 'text-red-600' : ($inv->isLowStock() ? 'text-amber-600' : '') }}">{{ $inv->quantity }}</td>
                            <td class="px-4 py-3">{{ $inv->low_stock_threshold }}</td>
                            <td class="px-4 py-3">{{ $inv->vendorProduct?->product?->stock }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No inventory records.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $items->links() }}
    </div>
</x-admin-layout>
