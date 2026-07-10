@props(['vendor' => null])

@php
    $link = fn (string $route, bool $active, array $params = []) => $active
        ? 'border-gray-900 text-gray-900 dark:border-gray-100 dark:text-gray-100'
        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-200';
@endphp

<nav class="-mb-px flex flex-wrap gap-x-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
    @if($vendor)
        @foreach([
            ['admin.vendors.show', 'Profile', ['vendor' => $vendor->id]],
            ['admin.vendors.vendor-revenue', 'Revenue & Wallet', ['vendor' => $vendor]],
            ['admin.vendors.products', 'Products', ['vendor' => $vendor]],
            ['admin.vendors.orders', 'Orders', ['vendor' => $vendor]],
            ['admin.vendors.analytics', 'Analytics', ['vendor' => $vendor]],
            ['admin.vendors.activity', 'Activity', ['vendor' => $vendor]],
            ['admin.vendors.edit', 'Settings', ['vendor' => $vendor]],
        ] as [$route, $label, $params])
            <a href="{{ route($route, $params) }}"
               class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors {{ $link($route, request()->routeIs($route)) }}">
                {{ $label }}
            </a>
        @endforeach
    @else
        @foreach([
            ['admin.vendors.overview', 'Overview'],
            ['admin.vendors.index', 'All Vendors'],
            ['admin.vendors.pending', 'Pending'],
            ['admin.vendors.active', 'Active'],
            ['admin.vendors.suspended', 'Suspended'],
            ['admin.marketplace.products.index', 'Products'],
            ['admin.marketplace.orders.index', 'Orders'],
            ['admin.vendors.revenue', 'Revenue'],
            ['admin.vendors.insights', 'Analytics'],
            ['admin.vendors.reports', 'Reports'],
        ] as [$route, $label])
            <a href="{{ route($route) }}"
               class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors {{ $link($route, request()->routeIs($route)) }}">
                {{ $label }}
            </a>
        @endforeach
    @endif
</nav>
