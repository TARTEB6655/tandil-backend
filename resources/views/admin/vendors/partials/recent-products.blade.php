<div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/70">
    <div class="flex items-center justify-between border-b px-4 py-3 dark:border-gray-700">
        <h2 class="font-semibold">Recent Products</h2>
        <a href="{{ route('admin.vendors.products', $vendor) }}" class="text-sm text-indigo-600">View all</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40"><tr><th class="px-4 py-2 text-left">Product</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-right">Stock</th></tr></thead>
            <tbody>
                @forelse($recentProducts as $vp)
                    <tr class="border-t dark:border-gray-800"><td class="px-4 py-3">{{ $vp->product?->name }}</td><td class="px-4 py-3 capitalize">{{ $vp->status }}</td><td class="px-4 py-3 text-right">{{ $vp->inventory?->quantity ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-gray-500">No products.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
