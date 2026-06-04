<x-vendor-layout>
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Products</h1>
            <p class="mt-1 text-sm text-gray-500">Manage your marketplace catalog.</p>
        </div>
        <a href="{{ route('vendor.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Product</a>
    </div>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="rounded-lg border-gray-300 text-sm" />
        <select name="status" class="rounded-lg border-gray-300 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm text-white">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Price</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Stock</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Approval</th>
                    <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($products as $vp)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $vp->product?->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $vp->inventory?->quantity ?? 0 }}</td>
                        <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ $vp->status }}</td>
                        <td class="px-4 py-3 text-sm capitalize text-gray-600">{{ $vp->approval_status ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm">
                            <a href="{{ route('vendor.products.edit', $vp->id) }}" class="text-indigo-600 hover:text-indigo-800">Edit</a>
                            <form method="POST" action="{{ route('vendor.products.destroy', $vp->id) }}" class="inline" onsubmit="return confirm('Delete this product?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-2 text-red-600 hover:text-red-800">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($products->hasPages())
            <div class="border-t border-gray-200 px-4 py-3">{{ $products->links() }}</div>
        @endif
    </div>
</x-vendor-layout>
