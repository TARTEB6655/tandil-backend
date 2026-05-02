@php
    $ship = $checkout->shipping_json ?? [];
    $currency = strtoupper((string) $checkout->currency);
    $street = $ship['street_address'] ?? $ship['street'] ?? '';
@endphp
<x-admin-layout>
    <div class="space-y-6 max-w-4xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Open mobile checkout</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Stripe payment intent not yet completed</p>
            </div>
            <a href="{{ route('admin.payments.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">← Transactions</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">Incomplete</span>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Amount</dt>
                    <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $checkout->total_amount, 2) }} {{ $currency }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Source</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $checkout->source }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-gray-500 dark:text-gray-400">Payment intent</dt>
                    <dd class="font-mono text-xs break-all text-gray-800 dark:text-gray-200">{{ $checkout->stripe_payment_intent_id ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">User</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $checkout->user?->email ?? ('User #'.$checkout->user_id) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $checkout->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                </div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Shipping</h2>
            <dl class="space-y-2 text-sm">
                <div><span class="text-gray-500">Name:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ship['full_name'] ?? '—' }}</span></div>
                <div><span class="text-gray-500">Phone:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ship['phone_number'] ?? $ship['phone'] ?? '—' }}</span></div>
                <div><span class="text-gray-500">Address:</span> <span class="font-medium text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ trim($street."\n".($ship['line2'] ?? '')) ?: '—' }}</span></div>
                <div><span class="text-gray-500">City / state / zip:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ ($ship['city'] ?? '').', '.($ship['state'] ?? '').' '.($ship['zip_code'] ?? $ship['zip'] ?? '') }}</span></div>
                <div><span class="text-gray-500">Country:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ $ship['country'] ?? '—' }}</span></div>
            </dl>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Cart lines (JSON)</h2>
            <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-800 dark:text-gray-200">{{ json_encode($checkout->lines_json ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
</x-admin-layout>
