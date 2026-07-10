<x-admin.vendor.action-menu>
    <x-admin.vendor.menu-link :href="route('admin.vendors.products.show', [$vendor, $vp])">View details</x-admin.vendor.menu-link>
    <x-admin.vendor.menu-link :href="route('admin.marketplace.products.edit', $vp)">Edit product</x-admin.vendor.menu-link>
    @if($vp->disabled_by_admin || $vp->status !== 'active')
        <form method="POST" action="{{ route('admin.vendors.products.enable', [$vendor, $vp]) }}">@csrf
            <x-admin.vendor.menu-button type="submit">Enable</x-admin.vendor.menu-button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.vendors.products.disable', [$vendor, $vp]) }}" onsubmit="return confirm('Disable this product on the marketplace?')">@csrf
            <x-admin.vendor.menu-button type="submit">Disable</x-admin.vendor.menu-button>
        </form>
    @endif
    <form method="POST" action="{{ route('admin.vendors.products.feature', [$vendor, $vp]) }}">@csrf
        <x-admin.vendor.menu-button type="submit">{{ $vp->product?->is_featured ? 'Unfeature' : 'Feature' }}</x-admin.vendor.menu-button>
    </form>
    <form method="POST" action="{{ route('admin.vendors.products.destroy', [$vendor, $vp]) }}" onsubmit="return confirm('Permanently remove this product listing?')">@csrf @method('DELETE')
        <x-admin.vendor.menu-button type="submit" :danger="true">Delete</x-admin.vendor.menu-button>
    </form>
</x-admin.vendor.action-menu>
