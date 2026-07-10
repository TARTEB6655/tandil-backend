@props(['vendor' => null])

@php
    $tab = fn (string $route, array $params = []) => request()->routeIs($route)
        ? 'bg-indigo-600 text-white shadow-sm'
        : 'bg-white/70 text-gray-600 hover:bg-gray-100 dark:bg-gray-800/70 dark:text-gray-300 dark:hover:bg-gray-800';
@endphp

@if($vendor)
    <nav class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-gray-200/80 bg-white/60 p-2 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/60">
        <a href="{{ route('admin.vendors.show', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.show', ['vendor' => $vendor->id]) }}">Overview</a>
        <a href="{{ route('admin.vendors.products', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.products') }}">Products</a>
        <a href="{{ route('admin.vendors.orders', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.orders') }}">Orders</a>
        <a href="{{ route('admin.vendors.vendor-revenue', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.vendor-revenue') }}">Revenue</a>
        <a href="{{ route('admin.vendors.activity', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.activity') }}">Activity</a>
        <a href="{{ route('admin.vendors.analytics', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.analytics') }}">Analytics</a>
        <a href="{{ route('admin.vendors.edit', $vendor) }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.edit') }}">Settings</a>
    </nav>
@else
    <nav class="mb-6 flex flex-wrap gap-2 rounded-2xl border border-gray-200/80 bg-white/60 p-2 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/60">
        <a href="{{ route('admin.vendors.index') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.index') }}">All Vendors</a>
        <a href="{{ route('admin.vendors.pending') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.pending') }}">Pending Approvals</a>
        <a href="{{ route('admin.vendors.active') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.active') }}">Active Vendors</a>
        <a href="{{ route('admin.vendors.suspended') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.suspended') }}">Suspended</a>
        <a href="{{ route('admin.vendors.insights') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.insights') }}">Analytics</a>
        <a href="{{ route('admin.vendors.revenue') }}" class="rounded-xl px-3 py-2 text-sm font-medium transition {{ $tab('admin.vendors.revenue') }}">Revenue</a>
    </nav>
@endif
