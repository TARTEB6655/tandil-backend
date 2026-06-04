@php
    $link = fn (string $route) => request()->routeIs($route)
        ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-semibold'
        : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800';
@endphp
<nav class="flex flex-wrap gap-2 mb-6 p-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
    <a href="{{ route('admin.marketplace.dashboard') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.marketplace.dashboard') }}">Overview</a>
    <a href="{{ route('admin.vendors.index') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.vendors.*') }}">Vendors</a>
    <a href="{{ route('admin.marketplace.products.index') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.marketplace.products.*') }}">Products</a>
    <a href="{{ route('admin.marketplace.orders.index') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.marketplace.orders.*') }}">Orders</a>
    <a href="{{ route('admin.marketplace.inventory.index') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.marketplace.inventory.*') }}">Inventory</a>
    <a href="{{ route('admin.marketplace.settings') }}" class="px-3 py-2 text-sm rounded-lg {{ $link('admin.marketplace.settings') }}">Pricing &amp; Commission</a>
</nav>
