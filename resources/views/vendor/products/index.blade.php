<x-vendor-layout>
    <x-dashboard.page-header title="Products" subtitle="Manage your marketplace catalog.">
        <x-slot:actions>
            <a href="{{ route('vendor.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Add Product</a>
        </x-slot:actions>
    </x-dashboard.page-header>

    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search products..." class="h-11 rounded-lg border-gray-200 bg-gray-50 text-sm focus:border-gray-300 focus:bg-white focus:ring-1 focus:ring-gray-300" />
        <select name="status" class="h-11 rounded-lg border-gray-200 bg-gray-50 text-sm">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select>
        <button type="submit" class="h-11 rounded-lg bg-gray-900 px-4 text-sm font-medium text-white hover:bg-gray-800">Filter</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
        <table class="min-w-[640px] w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Product</th>
                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Price</th>
                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Stock</th>
                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Status</th>
                    <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Approval</th>
                    <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 sm:px-6">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                @forelse($products as $vp)
                    <tr class="transition-colors hover:bg-gray-50">
                        <td class="px-3 py-3 text-sm font-medium text-gray-900 sm:px-6">{{ $vp->product?->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ $vp->stockQuantity() }}</td>
                        <td class="px-4 py-3 text-sm">
                            @if($vp->disabled_by_admin)
                                <span class="inline-flex rounded-md bg-rose-50 px-2 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-rose-600/20">Disabled by Admin</span>
                            @else
                                <span class="capitalize text-gray-600">{{ $vp->status }}</span>
                            @endif
                        </td>
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
            <div class="border-t border-gray-200 px-4 py-3 sm:px-6">{{ $products->links() }}</div>
        @endif
        </div>
    </div>
</x-vendor-layout>
