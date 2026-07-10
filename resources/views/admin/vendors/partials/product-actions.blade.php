<x-admin.vendor.action-menu>
    @if($vp->approval_status === 'pending')
        <form method="POST" action="{{ route('admin.vendors.products.approve', [$vendor, $vp]) }}">@csrf
            <x-admin.vendor.menu-button type="submit">Approve</x-admin.vendor.menu-button>
        </form>
        <form method="POST" action="{{ route('admin.vendors.products.reject', [$vendor, $vp]) }}" class="px-3 py-2">
            @csrf
            <input name="reason" required placeholder="Rejection reason" class="mb-2 w-full rounded-md border-gray-300 text-xs dark:border-gray-700 dark:bg-gray-900" />
            <x-admin.vendor.menu-button type="submit" :danger="true">Reject</x-admin.vendor.menu-button>
        </form>
    @endif
    <form method="POST" action="{{ route('admin.vendors.products.toggle', [$vendor, $vp]) }}">@csrf
        <x-admin.vendor.menu-button type="submit">{{ $vp->status === 'active' ? 'Disable' : 'Enable' }}</x-admin.vendor.menu-button>
    </form>
    <form method="POST" action="{{ route('admin.vendors.products.feature', [$vendor, $vp]) }}">@csrf
        <x-admin.vendor.menu-button type="submit">{{ $vp->product?->is_featured ? 'Unfeature' : 'Feature' }}</x-admin.vendor.menu-button>
    </form>
    <form method="POST" action="{{ route('admin.vendors.products.destroy', [$vendor, $vp]) }}" onsubmit="return confirm('Delete this product?')">@csrf @method('DELETE')
        <x-admin.vendor.menu-button type="submit" :danger="true">Delete</x-admin.vendor.menu-button>
    </form>
</x-admin.vendor.action-menu>
