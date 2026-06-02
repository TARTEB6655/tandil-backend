<x-client-layout>
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-medium text-gray-900">Shopping Cart</h1>
        <p class="mt-1 text-sm text-gray-500">Review your items before checkout.</p>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    @if($cartItems->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <svg class="w-20 h-20 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Your cart is empty</h3>
            <p class="text-sm text-gray-500 mb-6">Start shopping to add items to your cart.</p>
            <a href="{{ route('client.shop.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                Continue Shopping
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Cart Items -->
            <div class="lg:col-span-2 space-y-4">
                @foreach($cartItems as $item)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                        <div class="flex gap-4">
                            <!-- Product Image -->
                            <div class="flex-shrink-0">
                                <div class="h-24 w-24 rounded-lg overflow-hidden bg-gray-100">
                                    @if($item->product->getImageUrl())
                                        <img src="{{ $item->product->getImageUrl() }}" 
                                             alt="{{ $item->product->name }}" 
                                             class="h-full w-full object-cover">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            
                            <!-- Product Details -->
                            <div class="flex-1">
                                <h3 class="text-base font-semibold text-gray-900 mb-1">{{ $item->product->name }}</h3>
                                @if($item->product->category)
                                    <p class="text-xs text-gray-500 mb-2">{{ $item->product->category->name }}</p>
                                @endif
                                @php $unitPrice = $item->unit_price ?? $item->product->price; @endphp
                                <p class="text-lg font-bold text-indigo-600 mb-1">AED {{ number_format($unitPrice, 2) }}</p>
                                @if(!empty($item->selected_options))
                                    <p class="text-xs text-gray-500 mb-2">Configured variant</p>
                                @endif
                                
                                <!-- Quantity & Remove -->
                                <div class="flex items-center gap-4">
                                    <form action="{{ route('client.cart.update', $item->id) }}" method="POST" class="flex items-center gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="text-sm text-gray-600">Qty:</label>
                                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ $item->product->stock }}" 
                                               class="w-20 px-2 py-1 border border-gray-300 rounded-md text-sm" 
                                               onchange="this.form.submit()">
                                    </form>
                                    <form action="{{ route('client.cart.remove', $item->id) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Item Total -->
                            <div class="text-right">
                                <p class="text-lg font-bold text-gray-900">AED {{ number_format($item->quantity * $unitPrice, 2) }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
                
                <div class="flex justify-end">
                    <form action="{{ route('client.cart.clear') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear your cart?');">
                        @csrf
                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                            Clear Cart
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sticky top-4">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                    
                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="text-gray-900 font-medium">AED {{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tax ({{ $taxPercent ?? 5 }}%)</span>
                            <span class="text-gray-900 font-medium">AED {{ number_format($tax ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Shipping</span>
                            <span class="text-gray-900 font-medium">{{ $shipping > 0 ? 'AED ' . number_format($shipping, 2) : ($shippingLabel ?? 'Free') }}</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-base font-semibold text-gray-900">Total</span>
                                <span class="text-xl font-bold text-gray-900">AED {{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <a href="{{ route('client.checkout.index') }}" class="block w-full text-center px-4 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                        Proceed to Checkout
                    </a>
                    
                    <a href="{{ route('client.shop.index') }}" class="block w-full text-center mt-3 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    @endif
</x-client-layout>

