<x-vendor-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Vendor Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500">{{ $vendor->profile?->business_name ?? 'Your store' }}</p>
    </div>

    <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
        @foreach($statCards as $card)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-medium text-gray-900">Recent Orders</h2>
                <a href="{{ route('vendor.orders.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse($stats['recent_orders'] as $order)
                    <li class="flex items-center justify-between py-3 text-sm">
                        <span class="font-medium text-gray-900">#{{ $order['order_id'] }} — {{ $order['customer_name'] ?? 'Guest' }}</span>
                        <span class="text-gray-600">{{ ucfirst($order['status']) }} · AED {{ number_format($order['total_amount'], 2) }}</span>
                    </li>
                @empty
                    <li class="py-4 text-sm text-gray-500">No orders yet.</li>
                @endforelse
            </ul>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-medium text-gray-900">Inventory Alerts</h2>
                <a href="{{ route('vendor.inventory.index', ['filter' => 'low']) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Manage</a>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse($stats['inventory_alerts'] as $alert)
                    <li class="py-3 text-sm text-gray-700">
                        <span class="font-medium">{{ $alert['product_name'] }}</span>
                        — {{ $alert['quantity'] }} left (threshold {{ $alert['low_stock_threshold'] }})
                    </li>
                @empty
                    <li class="py-4 text-sm text-gray-500">No low-stock alerts.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('vendor.products.create') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-center text-sm font-medium text-indigo-700 hover:bg-indigo-100">+ Add Product</a>
        <a href="{{ route('vendor.orders.index') }}" class="rounded-xl border border-gray-200 bg-white p-4 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">View Orders</a>
        <a href="{{ route('vendor.profile.show') }}" class="rounded-xl border border-gray-200 bg-white p-4 text-center text-sm font-medium text-gray-700 hover:bg-gray-50">Edit Profile</a>
    </div>
</x-vendor-layout>
