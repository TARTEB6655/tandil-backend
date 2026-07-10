<div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent products</h2>
        <a href="{{ route('admin.vendors.products', $vendor) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all</a>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($recentProducts as $vp)
            <div class="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                @if($vp->product?->image_url)
                    <img src="{{ $vp->product->image_url }}" alt="" class="h-10 w-10 rounded-md border object-cover dark:border-gray-700" />
                @else
                    <x-admin.vendor.avatar :name="$vp->product?->name ?? 'P'" size="sm" />
                @endif
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ $vp->product?->name }}</p>
                    <div class="mt-1 flex items-center gap-2">
                        <x-admin.vendor.status-badge :status="$vp->status" />
                        <span class="text-xs text-gray-500">{{ $vp->inventory?->quantity ?? 0 }} in stock</span>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-500">No products yet.</div>
        @endforelse
    </div>
</div>
