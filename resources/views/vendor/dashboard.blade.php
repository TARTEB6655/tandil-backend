<x-vendor-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Vendor Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $vendor->profile?->business_name }}</p>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach([
                ['Total Products', $stats['total_products']],
                ['Active Products', $stats['active_products']],
                ['Out of Stock', $stats['out_of_stock_products']],
                ['Low Stock', $stats['low_stock_products']],
                ['Total Orders', $stats['total_orders']],
                ['Pending Orders', $stats['pending_orders']],
                ['Completed', $stats['completed_orders']],
                ['Revenue (AED)', number_format($stats['revenue'], 2)],
            ] as [$label, $value])
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <p class="text-xs text-gray-500 uppercase">{{ $label }}</p>
                    <p class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $value }}</p>
                </div>
            @endforeach
        </div>
        <div class="grid lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h2 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Recent Orders</h2>
                <ul class="space-y-2 text-sm">
                    @forelse($stats['recent_orders'] as $order)
                        <li class="flex justify-between border-b border-gray-100 dark:border-gray-700 pb-2">
                            <span>#{{ $order['order_id'] }} — {{ $order['customer_name'] ?? 'Guest' }}</span>
                            <span class="text-gray-600">{{ ucfirst($order['status']) }} · AED {{ number_format($order['total_amount'], 2) }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No orders yet.</li>
                    @endforelse
                </ul>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <h2 class="font-medium text-gray-900 dark:text-gray-100 mb-3">Inventory Alerts</h2>
                <ul class="space-y-2 text-sm">
                    @forelse($stats['inventory_alerts'] as $alert)
                        <li>{{ $alert['product_name'] }} — {{ $alert['quantity'] }} left (threshold {{ $alert['low_stock_threshold'] }})</li>
                    @empty
                        <li class="text-gray-500">No low-stock alerts.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-vendor-layout>
