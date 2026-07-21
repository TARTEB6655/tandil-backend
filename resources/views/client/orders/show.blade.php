@php
    $shipping = $order->getShippingAddressForApi();
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Order Details</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Order #{{ $order->id }}</p>
            </div>
            <a href="{{ route('client.orders.index') }}" class="text-xs sm:text-sm text-gray-600 hover:text-gray-900">
                ← Back to Orders
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-4 sm:space-y-6">
            <!-- Order Items -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Order Items</h2>
                <div class="space-y-4">
                    @forelse($order->items as $item)
                        @php
                            $product = $item->product;
                            $productImageUrl = $product?->image_url;
                            if (! $productImageUrl && ! empty($product?->image)) {
                                $rawImage = (string) $product->image;
                                if (str_starts_with($rawImage, 'http://') || str_starts_with($rawImage, 'https://')) {
                                    $productImageUrl = str_contains($rawImage, '/storage/products/')
                                        ? str_replace('/storage/products/', '/media/products/', $rawImage)
                                        : $rawImage;
                                } else {
                                    $normalizedPath = ltrim(str_replace('\\', '/', $rawImage), '/');
                                    if (! str_starts_with($normalizedPath, 'products/')) {
                                        $normalizedPath = 'products/' . $normalizedPath;
                                    }
                                    $productImageUrl = asset('media/' . $normalizedPath);
                                }
                            }
                        @endphp
                        <div class="flex gap-4 pb-4 border-b border-gray-200 last:border-0">
                            <div class="flex-shrink-0">
                                @if($productImageUrl)
                                    <img src="{{ $productImageUrl }}"
                                         alt="{{ $product?->name ?? 'Product image' }}"
                                         class="w-20 h-20 object-cover rounded-lg border border-gray-200">
                                @else
                                    <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1">
                                <h3 class="text-sm font-medium text-gray-900">{{ $item->product ? $item->product->name : 'Product #' . $item->product_id }}</h3>
                                @if($item->product && $item->product->category)
                                    <p class="text-xs text-gray-500">{{ $item->product->category->name }}</p>
                                @endif
                                <div class="flex items-center justify-between mt-2">
                                    <p class="text-sm text-gray-600">Qty: {{ $item->quantity }}</p>
                                    <p class="text-sm font-semibold text-gray-900">AED {{ number_format($item->subtotal, 2) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 text-center py-4">No items in this order.</p>
                    @endforelse
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Shipping Address</h2>
                @if($shipping)
                    <div class="space-y-3 text-sm text-gray-700">
                        @if(!empty($shipping['full_name']))
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Full Name</p>
                                <p class="font-medium text-gray-900">{{ $shipping['full_name'] }}</p>
                            </div>
                        @endif
                        @if(!empty($shipping['phone_number']))
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Phone</p>
                                <p class="font-medium text-gray-900">{{ $shipping['phone_number'] }}</p>
                            </div>
                        @endif
                        @if(!empty($shipping['street_address']))
                            <div>
                                <p class="text-xs text-gray-500 mb-1">Street Address</p>
                                <p class="font-medium text-gray-900">{{ $shipping['street_address'] }}</p>
                            </div>
                        @endif
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @if(!empty($shipping['city']))
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">City</p>
                                    <p class="font-medium text-gray-900">{{ $shipping['city'] }}</p>
                                </div>
                            @endif
                            @if(!empty($shipping['state']))
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">State</p>
                                    <p class="font-medium text-gray-900">{{ $shipping['state'] }}</p>
                                </div>
                            @endif
                            @if(!empty($shipping['zip_code']))
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">ZIP Code</p>
                                    <p class="font-medium text-gray-900">{{ $shipping['zip_code'] }}</p>
                                </div>
                            @endif
                            @if(!empty($shipping['country']))
                                <div>
                                    <p class="text-xs text-gray-500 mb-1">Country</p>
                                    <p class="font-medium text-gray-900">{{ $shipping['country'] }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-500">No shipping address available for this order.</p>
                @endif
            </div>

            @if($order->special_instructions)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Special Instructions</h2>
                <p class="text-sm text-gray-700">{{ $order->special_instructions }}</p>
            </div>
            @endif

            <!-- Order Timeline -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h2 class="text-base sm:text-lg font-semibold text-gray-900 mb-3 sm:mb-4">Order Timeline</h2>
                @php
                    $rawStatus = strtolower((string) ($order->order_status ?? 'pending'));
                    $normalizedStatus = match ($rawStatus) {
                        'paid' => 'confirmed',
                        'processing' => 'in_progress',
                        'shipped' => 'completed',
                        default => $rawStatus,
                    };
                    $rank = match ($normalizedStatus) {
                        'pending' => 0,
                        'confirmed' => 1,
                        'assigned' => 2,
                        'in_progress' => 3,
                        'completed' => 4,
                        'delivered' => 5,
                        default => 0,
                    };

                    $timeline = [
                        ['key' => 'pending', 'label' => 'Pending', 'description' => 'Order placed successfully', 'completed' => true, 'time' => $order->created_at?->format('h:i A')],
                        ['key' => 'confirmed', 'label' => 'Confirmed', 'description' => 'Order confirmed by our team', 'completed' => $rank >= 1, 'time' => ($order->paid_at ?? $order->updated_at)?->format('h:i A')],
                        ['key' => 'assigned', 'label' => 'Assigned', 'description' => 'Technician assigned to your order', 'completed' => $rank >= 2, 'time' => $order->updated_at?->format('h:i A')],
                        ['key' => 'in_progress', 'label' => 'In Progress', 'description' => 'Your order is being processed', 'completed' => $rank >= 3, 'time' => $order->updated_at?->format('h:i A')],
                        ['key' => 'completed', 'label' => 'Completed', 'description' => 'Your order is ready', 'completed' => $rank >= 4, 'time' => $order->updated_at?->format('h:i A')],
                        ['key' => 'delivered', 'label' => 'Delivered', 'description' => 'Delivered', 'completed' => $rank >= 5, 'time' => $order->updated_at?->format('h:i A')],
                    ];
                @endphp
                <div class="space-y-0">
                    @foreach($timeline as $idx => $step)
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold {{ $step['completed'] ? 'bg-green-600 text-white' : 'bg-white border border-gray-300 text-gray-400' }}">
                                    {{ $step['completed'] ? '✓' : '' }}
                                </span>
                                @if($idx !== count($timeline) - 1)
                                    <span class="w-0.5 h-8 {{ $step['completed'] ? 'bg-green-700' : 'bg-gray-200' }}"></span>
                                @endif
                            </div>
                            <div class="pb-4">
                                <p class="text-sm font-semibold {{ $step['completed'] ? 'text-green-800' : 'text-gray-500' }}">{{ $step['label'] }}</p>
                                <p class="text-sm {{ $step['completed'] ? 'text-blue-900/70' : 'text-gray-400' }}">{{ $step['description'] }}</p>
                                @if(!empty($step['time']) && $step['completed'])
                                    <p class="text-xs text-gray-500 mt-1">{{ $step['time'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                @if(strtolower((string) $order->order_status) === 'cancelled')
                    <div class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700">
                        Order was cancelled. If eligible, refund is processed to your wallet as per policy.
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-4 sm:space-y-6">
            <!-- Order Summary -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Order Summary</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900 font-medium">AED {{ number_format((float) ($order->subtotal_amount ?? $order->items->sum('subtotal')), 2) }}</span>
                    </div>
                    @if((float) ($order->shipping_amount ?? 0) > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Shipping</span>
                        <span class="text-gray-900 font-medium">AED {{ number_format((float) $order->shipping_amount, 2) }}</span>
                    </div>
                    @endif
                    @if((float) ($order->tax_amount ?? 0) > 0)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Tax @if($order->tax_percent)({{ $order->tax_percent }}%)@endif</span>
                        <span class="text-gray-900 font-medium">AED {{ number_format((float) $order->tax_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="border-t border-gray-200 pt-3">
                        <div class="flex justify-between">
                            <span class="text-base font-semibold text-gray-900">Total</span>
                            <span class="text-xl font-bold text-gray-900">AED {{ number_format($order->total_amount, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Status -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Status</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Order Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            @if($order->order_status === 'delivered') bg-green-100 text-green-800
                            @elseif($order->order_status === 'processing') bg-blue-100 text-blue-800
                            @else bg-yellow-100 text-yellow-800
                            @endif">
                            {{ ucfirst($order->order_status) }}
                        </span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Payment Status</p>
                        <span class="px-2 py-1 text-xs font-medium rounded-full 
                            {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                    @if($order->payment_method)
                    <div>
                        <p class="text-xs text-gray-500 mb-1">Payment Method</p>
                        <p class="text-sm font-medium text-gray-900">{{ ucfirst(str_replace('_', ' ', $order->payment_method)) }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Payment Information -->
            @if($order->payment_reference)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-6">
                <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-3 sm:mb-4">Payment Reference</h3>
                <p class="text-sm font-mono text-gray-700 break-all">{{ $order->payment_reference }}</p>
            </div>
            @endif
        </div>
    </div>
</x-client-layout>

