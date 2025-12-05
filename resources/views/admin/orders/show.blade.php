<x-admin-layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Order Details</h1>
                <p class="text-sm text-gray-500 mt-1">Order #{{ $order->id }}</p>
            </div>
            <a href="{{ route('admin.orders.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                ← Back to Orders
            </a>
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-base font-medium text-gray-900">Order Items</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($order->items as $item)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center gap-4">
                                                @if($item->product->image)
                                                    <img src="{{ asset('storage/' . $item->product->image) }}" 
                                                         alt="{{ $item->product->name }}" 
                                                         class="h-16 w-16 object-cover rounded-lg border border-gray-200">
                                                @else
                                                    <div class="h-16 w-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                @endif
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{ $item->product->name }}</div>
                                                    <div class="text-xs text-gray-500">{{ $item->product->category->name ?? 'N/A' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">AED {{ number_format($item->price, 2) }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-900">{{ $item->quantity }}</td>
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">AED {{ number_format($item->subtotal, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">No items found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-right text-sm font-medium text-gray-900">Total:</td>
                                    <td class="px-6 py-4 text-sm font-semibold text-gray-900">AED {{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Order Status -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Order Status</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Status</label>
                            <form method="POST" action="{{ route('admin.orders.update-status', $order->id) }}" class="mb-3">
                                @csrf
                                <select name="order_status" onchange="this.form.submit()" class="w-full rounded-lg border-gray-300 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="pending" {{ $order->order_status === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="processing" {{ $order->order_status === 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="shipped" {{ $order->order_status === 'shipped' ? 'selected' : '' }}>Shipped</option>
                                    <option value="delivered" {{ $order->order_status === 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="cancelled" {{ $order->order_status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </form>
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $order->order_status === 'delivered' ? 'bg-green-100 text-green-800' : 
                                   ($order->order_status === 'processing' ? 'bg-blue-100 text-blue-800' : 
                                   ($order->order_status === 'shipped' ? 'bg-purple-100 text-purple-800' : 
                                   ($order->order_status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800'))) }}">
                                {{ ucfirst($order->order_status) }}
                            </span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Status</label>
                            @if($order->payment_status !== 'paid' && $order->payment_status !== 'refunded')
                                <form method="POST" action="{{ route('admin.orders.mark-paid', $order->id) }}" class="mb-3">
                                    @csrf
                                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        Mark as Paid
                                    </button>
                                </form>
                            @endif
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full 
                                {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 
                                   ($order->payment_status === 'failed' ? 'bg-red-100 text-red-800' : 
                                   ($order->payment_status === 'refunded' ? 'bg-gray-100 text-gray-800' : 'bg-yellow-100 text-yellow-800')) }}">
                                {{ ucfirst($order->payment_status) }}
                            </span>
                        </div>
                        
                        <!-- Cancel Order -->
                        @if(!in_array($order->order_status, ['cancelled', 'delivered']))
                            <div class="pt-4 border-t border-gray-200">
                                <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}" 
                                      onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                    @csrf
                                    <button type="submit" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                        Cancel Order
                                    </button>
                                </form>
                            </div>
                        @endif
                        
                        <!-- Refund Order -->
                        @if($order->payment_status === 'paid' && !$order->refunded_at)
                            <div class="pt-4 border-t border-gray-200">
                                <button type="button" 
                                        onclick="document.getElementById('refundModal').classList.remove('hidden')"
                                        class="w-full px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                                    Process Refund
                                </button>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Customer Information -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Customer Information</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Name</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->user->name ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->user->email ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->user->phone ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                <!-- Order Information -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h3 class="text-base font-medium text-gray-900 mb-4">Order Information</h3>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-gray-500">Order ID</p>
                            <p class="text-sm font-medium text-gray-900">#{{ $order->id }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Order Date</p>
                            <p class="text-sm font-medium text-gray-900">{{ $order->created_at->format('M d, Y h:i A') }}</p>
                        </div>
                        @if($order->paid_at)
                            <div>
                                <p class="text-xs text-gray-500">Paid At</p>
                                <p class="text-sm font-medium text-gray-900">{{ $order->paid_at->format('M d, Y h:i A') }}</p>
                            </div>
                        @endif
                        @if($order->payment_reference)
                            <div>
                                <p class="text-xs text-gray-500">Payment Reference</p>
                                <p class="text-sm font-medium text-gray-900">{{ $order->payment_reference }}</p>
                            </div>
                        @endif
                        @if($order->payment_method)
                            <div>
                                <p class="text-xs text-gray-500">Payment Method</p>
                                <p class="text-sm font-medium text-gray-900">{{ ucfirst($order->payment_method) }}</p>
                            </div>
                        @endif
                        @if($order->transaction_id)
                            <div>
                                <p class="text-xs text-gray-500">Transaction ID</p>
                                <p class="text-sm font-medium text-gray-900">{{ $order->transaction_id }}</p>
                            </div>
                        @endif
                        @if($order->refunded_at)
                            <div>
                                <p class="text-xs text-gray-500">Refunded At</p>
                                <p class="text-sm font-medium text-gray-900">{{ $order->refunded_at->format('M d, Y h:i A') }}</p>
                            </div>
                            @if($order->refund_amount)
                                <div>
                                    <p class="text-xs text-gray-500">Refund Amount</p>
                                    <p class="text-sm font-medium text-red-600">AED {{ number_format($order->refund_amount, 2) }}</p>
                                </div>
                            @endif
                        @endif
                        <div>
                            <p class="text-xs text-gray-500">Total Amount</p>
                            <p class="text-lg font-medium text-gray-900">AED {{ number_format($order->total_amount, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Refund Modal -->
        <div id="refundModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Process Refund</h3>
                <form method="POST" action="{{ route('admin.orders.refund', $order->id) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Refund Amount (Max: AED {{ number_format($order->total_amount, 2) }})</label>
                            <input type="number" 
                                   name="refund_amount" 
                                   step="0.01" 
                                   max="{{ $order->total_amount }}"
                                   value="{{ $order->total_amount }}"
                                   required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Refund Reason (Optional)</label>
                            <textarea name="refund_reason" 
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6">
                        <button type="submit" class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                            Process Refund
                        </button>
                        <button type="button" 
                                onclick="document.getElementById('refundModal').classList.add('hidden')"
                                class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin-layout>
