<x-admin-layout>
    <div class="space-y-6">
        <x-admin.vendor.nav :vendor="$vendor" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Products — {{ $vendor->profile?->business_name }}</h1>
                <p class="text-sm text-gray-500">Manage vendor catalog, approvals, and visibility.</p>
            </div>
        </div>

        @if(session('success'))<div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif

        <form method="GET" class="flex flex-wrap gap-2 rounded-2xl border bg-white/80 p-4 backdrop-blur dark:bg-gray-900/70">
            <input name="search" value="{{ request('search') }}" placeholder="Search products" class="rounded-xl border-gray-300 text-sm" />
            <select name="approval_status" class="rounded-xl border-gray-300 text-sm">
                <option value="">Approval</option>
                @foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" @selected(request('approval_status')===$s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <select name="status" class="rounded-xl border-gray-300 text-sm">
                <option value="">Status</option>
                @foreach(['active','inactive','draft'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <button class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </form>

        <div class="overflow-hidden rounded-2xl border bg-white/80 shadow-sm backdrop-blur dark:bg-gray-900/70">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-4 py-3 text-left">Product</th>
                            <th class="px-4 py-3 text-left">Category</th>
                            <th class="px-4 py-3 text-left">Approval</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Price</th>
                            <th class="px-4 py-3 text-right">Stock</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y dark:divide-gray-800">
                        @forelse($products as $vp)
                            <tr class="hover:bg-indigo-50/30">
                                <td class="px-4 py-3 font-medium">{{ $vp->product?->name }}</td>
                                <td class="px-4 py-3">{{ $vp->product?->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3"><x-admin.vendor.status-badge :status="$vp->approval_status" /></td>
                                <td class="px-4 py-3"><x-admin.vendor.status-badge :status="$vp->status" /></td>
                                <td class="px-4 py-3 text-right">AED {{ number_format($vp->currentPrice?->price ?? $vp->product?->price ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-right">{{ $vp->inventory?->quantity ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap justify-end gap-1">
                                        @if($vp->approval_status === 'pending')
                                            <form method="POST" action="{{ route('admin.vendors.products.approve', [$vendor, $vp]) }}">@csrf<button class="rounded-lg bg-emerald-600 px-2 py-1 text-xs text-white">Approve</button></form>
                                            <form method="POST" action="{{ route('admin.vendors.products.reject', [$vendor, $vp]) }}" class="inline-flex gap-1">@csrf<input name="reason" required placeholder="Reason" class="w-24 rounded border text-xs" /><button class="rounded-lg bg-rose-600 px-2 py-1 text-xs text-white">Reject</button></form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.vendors.products.toggle', [$vendor, $vp]) }}">@csrf<button class="rounded-lg border px-2 py-1 text-xs">{{ $vp->status === 'active' ? 'Disable' : 'Enable' }}</button></form>
                                        <form method="POST" action="{{ route('admin.vendors.products.feature', [$vendor, $vp]) }}">@csrf<button class="rounded-lg border px-2 py-1 text-xs">{{ $vp->product?->is_featured ? 'Unfeature' : 'Feature' }}</button></form>
                                        <form method="POST" action="{{ route('admin.vendors.products.destroy', [$vendor, $vp]) }}" onsubmit="return confirm('Delete product?')">@csrf @method('DELETE')<button class="rounded-lg bg-rose-700 px-2 py-1 text-xs text-white">Delete</button></form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-12 text-center text-gray-500">No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $products->links() }}
    </div>
</x-admin-layout>
