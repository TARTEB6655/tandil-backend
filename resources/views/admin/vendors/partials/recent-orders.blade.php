<x-admin.vendor.card :padding="false">
    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Recent orders</h2>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($recentOrders as $order)
            <div class="flex items-center justify-between gap-4 px-5 py-3 transition-colors hover:bg-gray-50/80 dark:hover:bg-gray-800/40">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Order #{{ $order['order_id'] }}</p>
                    <p class="text-xs text-gray-500">{{ $order['customer_name'] ?? 'Guest' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-medium tabular-nums text-gray-900 dark:text-gray-100">AED {{ number_format($order['total_amount'], 2) }}</p>
                    <p class="text-xs capitalize text-gray-500">{{ str_replace('_', ' ', $order['status']) }}</p>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-500">No orders yet.</div>
        @endforelse
    </div>
</x-admin.vendor.card>
