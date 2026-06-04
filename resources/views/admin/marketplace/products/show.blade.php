<x-admin-layout>
    <div class="space-y-6 max-w-4xl">
        @if(session('success'))<div class="p-3 bg-green-50 text-green-800 text-sm rounded-lg">{{ session('success') }}</div>@endif
        <x-admin.marketplace-nav />
        <h1 class="text-xl font-semibold">{{ $vendorProduct->product?->name }}</h1>
        <p class="text-sm text-gray-500">Vendor: {{ $vendorProduct->vendor?->profile?->business_name }} · Approval: {{ ucfirst($vendorProduct->approval_status ?? 'n/a') }}</p>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.marketplace.products.edit', $vendorProduct) }}" class="px-4 py-2 border rounded-lg text-sm">Edit</a>
            @if(($vendorProduct->approval_status ?? '') === 'pending')
                <form method="POST" action="{{ route('admin.marketplace.products.approve', $vendorProduct) }}">@csrf<button class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg">Approve</button></form>
                <form method="POST" action="{{ route('admin.marketplace.products.reject', $vendorProduct) }}" class="inline-flex gap-2">@csrf
                    <input name="reason" required placeholder="Rejection reason" class="rounded-lg border-gray-300 text-sm" />
                    <button class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg">Reject</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.marketplace.products.destroy', $vendorProduct) }}" onsubmit="return confirm('Remove this listing?')">@csrf @method('DELETE')
                <button class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg">Remove listing</button>
            </form>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-xl border p-6 text-sm space-y-2">
            <p><span class="text-gray-500">Category:</span> {{ $vendorProduct->product?->category?->name ?? '—' }}</p>
            <p><span class="text-gray-500">SKU:</span> {{ $vendorProduct->product?->sku ?? '—' }}</p>
            <p><span class="text-gray-500">Stock:</span> {{ $vendorProduct->inventory?->quantity ?? 0 }}</p>
            <p class="text-gray-700 dark:text-gray-300">{{ $vendorProduct->product?->description }}</p>
        </div>
    </div>
</x-admin-layout>
