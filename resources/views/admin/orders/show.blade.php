<x-admin-layout>
    <div class="space-y-6">
        <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-6">
            Order Details
        </h2>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Information</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Order ID</p>
                        <p class="text-sm font-medium text-gray-900">#{{ $order->id }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Customer</p>
                        <p class="text-sm font-medium text-gray-900">{{ $order->user->name ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Amount</p>
                        <p class="text-sm font-medium text-gray-900">AED {{ number_format($order->total_amount, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                            {{ ucfirst($order->order_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Payment Status</p>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $order->payment_status == 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Created At</p>
                        <p class="text-sm font-medium text-gray-900">{{ $order->created_at->format('M d, Y H:i') }}</p>
                    </div>
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Orders</a>
            </div>
        </div>
    </div>
</x-admin-layout>

