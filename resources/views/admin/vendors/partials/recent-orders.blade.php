<div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-900/70">
    <div class="flex items-center justify-between border-b px-4 py-3 dark:border-gray-700">
        <h2 class="font-semibold">Recent Orders</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-900/40"><tr><th class="px-4 py-2 text-left">Order</th><th class="px-4 py-2 text-left">Customer</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-right">Total</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-4 py-3 font-medium text-indigo-600">#{{ $order['order_id'] }}</td>
                        <td class="px-4 py-3">{{ $order['customer_name'] ?? 'Guest' }}</td>
                        <td class="px-4 py-3 capitalize">{{ str_replace('_', ' ', $order['status']) }}</td>
                        <td class="px-4 py-3 text-right">AED {{ number_format($order['total_amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No orders.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
