<x-admin-layout>
    <x-admin.vendor.shell>
        <x-admin.vendor.nav :vendor="$vendor" />
        <x-admin.vendor.flash />

        @php $product = $vendorProduct->product; @endphp

        <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-start gap-4">
                @if($product?->image_url)
                    <img src="{{ $product->image_url }}" alt="" class="h-20 w-20 rounded-lg border object-cover dark:border-gray-700" />
                @else
                    <x-admin.vendor.avatar :name="$product?->name ?? 'P'" size="lg" />
                @endif
                <div>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-50">{{ $product?->name }}</h1>
                    <p class="mt-1 text-sm text-gray-500">SKU: {{ $product?->sku ?? '—' }} · {{ $product?->category?->name ?? 'Uncategorized' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($product?->is_featured)<span class="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-amber-600/20">Featured</span>@endif
                        <span @class([
                            'rounded-md px-2 py-0.5 text-xs font-medium ring-1',
                            'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $vendorProduct->isMarketplaceVisible(),
                            'bg-gray-100 text-gray-600 ring-gray-500/20' => ! $vendorProduct->isMarketplaceVisible(),
                        ])>{{ $vendorProduct->isMarketplaceVisible() ? 'Live' : 'Hidden' }}</span>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-admin.vendor.btn variant="secondary" :href="route('admin.marketplace.products.edit', $vendorProduct)">Edit product</x-admin.vendor.btn>
                @include('admin.vendors.partials.product-actions', ['vendor' => $vendor, 'vp' => $vendorProduct])
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 md:grid-cols-4 lg:grid-cols-6">
            <x-admin.vendor.stat-card label="Price" :value="'AED '.number_format($vendorProduct->currentPrice?->price ?? $product?->price ?? 0, 2)" />
            <x-admin.vendor.stat-card label="Stock" :value="number_format($vendorProduct->inventory?->quantity ?? 0)" />
            <x-admin.vendor.stat-card label="Total Sales" :value="number_format($totalSales)" accent="text-indigo-600" />
            <x-admin.vendor.stat-card label="Marketplace" :value="$vendorProduct->isMarketplaceVisible() ? 'Visible' : 'Hidden'" :accent="$vendorProduct->isMarketplaceVisible() ? 'text-emerald-600' : 'text-rose-600'" />
            <x-admin.vendor.stat-card label="Created" :value="$vendorProduct->created_at?->format('M j, Y') ?? '—'" />
            <x-admin.vendor.stat-card label="Last Updated" :value="$vendorProduct->updated_at?->diffForHumans() ?? '—'" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-admin.vendor.card title="Product details" class="lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                    <div><dt class="text-xs font-medium uppercase text-gray-500">Description</dt><dd class="mt-1 text-gray-700 dark:text-gray-300">{{ $product?->description ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase text-gray-500">Product type</dt><dd class="mt-1 capitalize">{{ $product?->product_type ?? 'simple' }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase text-gray-500">Compare at price</dt><dd class="mt-1">AED {{ number_format($product?->compare_at_price ?? 0, 2) }}</dd></div>
                    <div><dt class="text-xs font-medium uppercase text-gray-500">Low stock threshold</dt><dd class="mt-1">{{ $vendorProduct->inventory?->low_stock_threshold ?? 5 }}</dd></div>
                </dl>
            </x-admin.vendor.card>

            <x-admin.vendor.card title="Marketplace control">
                <dl class="space-y-4 text-sm">
                    @if($vendorProduct->disabled_by_admin)
                        <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900/50 dark:bg-rose-950/30">
                            <p class="font-medium text-rose-800 dark:text-rose-200">Disabled by Admin</p>
                            <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ $vendorProduct->admin_disable_reason ?? 'No reason provided.' }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ $vendorProduct->disabled_by_admin_at?->format('M j, Y g:i A') }} · {{ $vendorProduct->disabledByAdminUser?->name ?? 'Admin' }}</p>
                        </div>
                    @endif
                </dl>

                <div class="mt-6 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    <form method="POST" action="{{ route($vendorProduct->isMarketplaceVisible() ? 'admin.vendors.products.disable' : 'admin.vendors.products.enable', [$vendor, $vendorProduct]) }}" onsubmit="return confirm('Change marketplace visibility?')">@csrf
                        <x-admin.vendor.btn variant="secondary" type="submit" class="w-full">{{ $vendorProduct->isMarketplaceVisible() ? 'Disable on marketplace' : 'Enable on marketplace' }}</x-admin.vendor.btn>
                    </form>
                </div>
            </x-admin.vendor.card>
        </div>
    </x-admin.vendor.shell>
</x-admin-layout>
