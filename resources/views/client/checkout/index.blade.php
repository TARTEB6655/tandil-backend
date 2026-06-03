<x-client-layout>
    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-medium text-gray-900">Checkout</h1>
        <p class="mt-1 text-sm text-gray-500">Complete your order and payment.</p>
    </div>

    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <form action="{{ route('client.checkout.process') }}" method="POST" id="checkoutForm">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Checkout Form -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Shipping Information -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Shipping Information</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Full Address *</label>
                            <textarea name="shipping_address" rows="3" required
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                      placeholder="Enter your complete address">{{ old('shipping_address', $user->address ?? '') }}</textarea>
                            @error('shipping_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                                <input type="text" name="shipping_city" required value="{{ old('shipping_city', $user->city ?? '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="City">
                                @error('shipping_city')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                                <input type="text" name="shipping_postal_code" value="{{ old('shipping_postal_code', $user->postal_code ?? '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Postal Code">
                                @error('shipping_postal_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country *</label>
                                <input type="text" name="shipping_country" required value="{{ old('shipping_country', $user->country ?? 'UAE') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Country">
                                @error('shipping_country')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input type="text" name="phone" required value="{{ old('phone', $user->phone ?? '') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                       placeholder="Phone Number">
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Method</h2>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-indigo-500 transition-colors">
                            <input type="radio" name="payment_method" value="stripe" checked class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-medium text-gray-900">Stripe</span>
                                    <svg class="w-8 h-8 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                </div>
                                <p class="text-sm text-gray-500 mt-1">Pay with card (secure checkout)</p>
                            </div>
                        </label>

                        <label class="flex items-center p-4 border-2 border-gray-200 rounded-lg cursor-pointer hover:border-indigo-500 transition-colors">
                            <input type="radio" name="payment_method" value="paypal" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
                            <div class="ml-3 flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-medium text-gray-900">PayPal</span>
                                    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_111x69.jpg" alt="PayPal" class="h-8">
                                </div>
                                <p class="text-sm text-gray-500 mt-1">Pay with your PayPal account</p>
                            </div>
                        </label>
                    </div>
                    
                    @error('payment_method')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Coupon</h2>
                    <div class="flex gap-2">
                        <input
                            type="text"
                            name="coupon_code"
                            value="{{ old('coupon_code', $appliedCouponCode ?? '') }}"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase"
                            placeholder="Enter coupon code (optional)"
                        >
                    </div>
                    @if(!empty($couponError))
                        <p class="mt-2 text-sm text-red-600">{{ $couponError }}</p>
                    @elseif(!empty($appliedCouponCode))
                        <p class="mt-2 text-sm text-green-700">Applied: {{ $appliedCouponCode }}</p>
                    @endif
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Cancellation & Refund Policy</h2>
                    <div class="space-y-2 text-sm text-gray-700">
                        <p>- Full refund before assignment/processing or within {{ $refundPolicy['grace_minutes'] ?? 15 }} minutes.</p>
                        <p>- Partial refund after assignment: {{ $refundPolicy['rules'][1]['refund_percent'] ?? 50 }}%.</p>
                        <p>- After service starts/completes, service fee applies and refund may be limited.</p>
                        <p>- Refunds are credited to wallet and expire after {{ $refundPolicy['wallet_terms']['expires_after_months'] ?? 6 }} months.</p>
                    </div>
                    <label class="mt-4 inline-flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="accepted_refund_policy" value="1" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" {{ old('accepted_refund_policy') ? 'checked' : '' }}>
                        <span>I understand and accept the cancellation and refund policy.</span>
                    </label>
                    @error('accepted_refund_policy')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sticky top-4">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h2>
                    
                    <!-- Cart Items -->
                    <div class="space-y-3 mb-4 max-h-64 overflow-y-auto">
                        @foreach($cartItems as $item)
                            <div class="flex gap-3">
                                <div class="flex-shrink-0 h-16 w-16 rounded-lg overflow-hidden bg-gray-100">
                                    @if($item->product->getImageUrl())
                                        <img src="{{ $item->product->getImageUrl() }}" 
                                             alt="{{ $item->product->name }}" 
                                             class="h-full w-full object-cover">
                                    @else
                                        <div class="h-full w-full flex items-center justify-center">
                                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">{{ $item->product->name }}</p>
                                    <p class="text-xs text-gray-500">Qty: {{ $item->quantity }}</p>
                                    @php
                                        $unitPrice = $item->lineUnitPrice();
                                        $optionLines = \App\Models\Cart::resolveSelectedOptionsDisplay($item->product, $item->selected_options);
                                    @endphp
                                    <x-shop.cart-selected-options
                                        :lines="$optionLines"
                                        class="mt-1.5 space-y-1"
                                    />
                                    <p class="text-sm font-semibold text-gray-900 tabular-nums mt-1">AED {{ number_format($item->quantity * $unitPrice, 2) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4 space-y-2">
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
                        <x-shop.category-shipping-breakdown :breakdown="$categoryShippingBreakdown ?? []" />
                        @if(($couponDiscount ?? 0) > 0)
                            <div class="flex justify-between text-sm">
                                <span class="text-green-700">Coupon Discount</span>
                                <span class="font-medium text-green-700">- AED {{ number_format($couponDiscount, 2) }}</span>
                            </div>
                        @endif
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-base font-semibold text-gray-900">Total</span>
                                <span class="text-xl font-bold text-gray-900">AED {{ number_format($total, 2) }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full mt-6 px-4 py-3 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors font-medium">
                        Complete Order
                    </button>
                    
                    <a href="{{ route('client.cart.index') }}" class="block w-full text-center mt-3 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Back to Cart
                    </a>
                </div>
            </div>
        </div>
    </form>
</x-client-layout>

