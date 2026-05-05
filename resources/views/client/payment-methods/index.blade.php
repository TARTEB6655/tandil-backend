<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Payment Methods</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Saved payment methods. Same as API GET /api/user/payment-methods.</p>
    </div>
    @if($paymentMethods->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
            <p class="text-gray-500">No saved payment methods yet.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
            @foreach($paymentMethods as $method)
                <div class="p-4 sm:p-5 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $method->label ?: ucfirst($method->gateway) }}
                            @if($method->is_default)
                                <span class="ml-2 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Default</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Gateway: {{ strtoupper($method->gateway) }}
                            @if($method->email) · {{ $method->email }} @endif
                            @if($method->last4) · •••• {{ $method->last4 }} @endif
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-client-layout>
