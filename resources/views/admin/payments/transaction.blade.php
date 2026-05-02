<x-admin-layout>
    <div class="space-y-6 max-w-3xl">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Transaction record</h1>
            <a href="{{ route('admin.payments.index') }}"
               class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">← Transactions</a>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4 text-sm">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Transaction ID</dt>
                    <dd class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ $transaction->transaction_id }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($transaction->type) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Gateway</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $transaction->gateway ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Status</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst($transaction->status) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Amount</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) $transaction->amount, 2) }} {{ $transaction->currency }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Created</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $transaction->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                </div>
                @if($transaction->processed_at)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Processed</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $transaction->processed_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            @if($transaction->notes)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-1">Notes</dt>
                    <dd class="text-gray-800 dark:text-gray-200">{{ $transaction->notes }}</dd>
                </div>
            @endif

            @if($transaction->transactionable instanceof \App\Models\Order)
                <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('admin.payments.order', $transaction->transactionable) }}"
                       class="text-indigo-600 dark:text-indigo-400 font-medium hover:underline">View related order payment →</a>
                </div>
            @endif

            @if(!empty($transaction->gateway_response))
                <div>
                    <dt class="text-gray-500 dark:text-gray-400 text-xs uppercase mb-2">Gateway response</dt>
                    <pre class="text-xs bg-gray-50 dark:bg-gray-900 p-4 rounded-lg overflow-x-auto text-gray-800 dark:text-gray-200">{{ json_encode($transaction->gateway_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
