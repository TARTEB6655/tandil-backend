@php
    $ps = $order->payment_status;
    $payLabel = match ($ps) {
        'paid' => 'Succeeded',
        'pending' => 'Incomplete / Pending',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
        default => ucfirst((string) $ps),
    };
    $payBadge = match ($ps) {
        'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
        'pending' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        'refunded' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
        default => 'bg-gray-100 text-gray-800',
    };
    $currency = strtoupper((string) config('shop.currency', 'AED'));
@endphp
<x-admin-layout>
    <div class="space-y-6 max-w-5xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Payment details</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Order #{{ $order->publicOrderNumberDigits() }} · {{ $order->publicOrderNumber() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.payments.index') }}"
                   class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">← Transactions</a>
                <a href="{{ route('admin.orders.show', $order) }}"
                   class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Full order</a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Customer</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->payerDisplayName() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->payerEmail() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->payerPhone() ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Address</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100 whitespace-pre-line">{{ $order->payerAddressForDisplay() ?: '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide">Payment</h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Amount</dt>
                        <dd class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $order->total_amount, 2) }} {{ $currency }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Method</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->paymentMethodLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Payment status</dt>
                        <dd><span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold {{ $payBadge }}">{{ $payLabel }}</span></dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Order status</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ ucfirst((string) $order->order_status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Placed at</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                    </div>
                    @if($order->paid_at)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Paid at</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->paid_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                        </div>
                    @endif
                    @if($order->refunded_at)
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Refunded at</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $order->refunded_at->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Refund amount</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) ($order->refund_amount ?? 0), 2) }} {{ $currency }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Reference</dt>
                        <dd class="font-mono text-xs break-all text-gray-800 dark:text-gray-200">{{ $order->payment_reference ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Gateway transaction id</dt>
                        <dd class="font-mono text-xs break-all text-gray-800 dark:text-gray-200">{{ $order->transaction_id ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        @if($order->transactions->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wide mb-4">Ledger (transactions table)</h2>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($order->transactions as $t)
                        <li class="py-3 flex flex-wrap justify-between gap-2 text-sm">
                            <a href="{{ route('admin.payments.transaction', $t->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline font-mono text-xs">{{ $t->transaction_id }}</a>
                            <span class="text-gray-600 dark:text-gray-300">{{ ucfirst($t->type) }} · {{ ucfirst($t->status) }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) $t->amount, 2) }} {{ $t->currency }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-admin-layout>
