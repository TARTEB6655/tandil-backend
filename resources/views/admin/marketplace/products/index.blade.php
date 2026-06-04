<x-admin-layout>
    <div class="space-y-6">
        <h1 class="text-xl font-semibold">Vendor products</h1>
        <x-admin.marketplace-nav />
        <form method="GET" class="flex flex-wrap gap-2">
            <input name="search" value="{{ request('search') }}" placeholder="Product name" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800" />
            <select name="approval_status" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                <option value="">All approval</option>
                @foreach(['pending','approved','rejected'] as $s)
                    <option value="{{ $s }}" @selected(request('approval_status')===$s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Filter</button>
        </form>
        <div class="bg-white dark:bg-gray-800 rounded-xl border overflow-x-auto">
            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-600">
                <thead class="bg-gray-50 dark:bg-gray-700/50"><tr>
                    <th class="px-4 py-3 text-left">Product</th><th class="px-4 py-3 text-left">Vendor</th><th class="px-4 py-3 text-left">Price</th><th class="px-4 py-3 text-left">Stock</th><th class="px-4 py-3 text-left">Approval</th><th class="px-4 py-3 text-right">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse($products as $vp)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <td class="px-4 py-3 font-medium">{{ $vp->product?->name }}</td>
                            <td class="px-4 py-3">{{ $vp->vendor?->profile?->business_name }}</td>
                            <td class="px-4 py-3">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</td>
                            <td class="px-4 py-3">{{ $vp->inventory?->quantity ?? 0 }}</td>
                            <td class="px-4 py-3"><span class="px-2 py-0.5 rounded text-xs bg-gray-100">{{ ucfirst($vp->approval_status ?? 'pending') }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('admin.marketplace.products.show', $vp) }}" class="text-indigo-600 hover:underline">Manage</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No vendor products.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </div>
</x-admin-layout>
