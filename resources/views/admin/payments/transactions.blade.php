@php
    $qBase = array_merge(request()->except(['page']), ['type' => $type]);
@endphp
<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Transactions</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Shop orders and payment status (similar to Stripe Payments)</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.payments.settings') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Gateway settings
                </a>
                @php
                    $exportParams = array_merge(request()->only(['date_from', 'date_to']), ['format' => 'csv']);
                    if ($tab !== 'all') {
                        $exportParams['payment_status'] = $tab;
                    }
                @endphp
                <a href="{{ route('admin.orders.export', $exportParams) }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                    Export CSV
                </a>
            </div>
        </div>

        <div class="border-b border-gray-200 dark:border-gray-700 flex gap-4 text-sm">
            <a href="{{ route('admin.payments.index', array_merge($qBase, ['type' => 'shop'])) }}"
               class="pb-3 -mb-px border-b-2 font-medium {{ $type === 'shop' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' }}">
                Shop
            </a>
            <a href="{{ route('admin.payments.index', array_merge($qBase, ['type' => 'all'])) }}"
               class="pb-3 -mb-px border-b-2 font-medium {{ $type === 'all' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200' }}">
                All orders
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach([
                'all' => ['label' => 'All', 'count' => $statusCounts['all']],
                'paid' => ['label' => 'Succeeded', 'count' => $statusCounts['paid']],
                'pending' => ['label' => 'Incomplete', 'count' => $statusCounts['pending']],
                'failed' => ['label' => 'Failed', 'count' => $statusCounts['failed']],
                'refunded' => ['label' => 'Refunded', 'count' => $statusCounts['refunded']],
            ] as $key => $card)
                <a href="{{ route('admin.payments.index', array_merge($qBase, ['tab' => $key])) }}"
                   class="rounded-xl border p-4 transition {{ $tab === $key ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-gray-300 dark:hover:border-gray-600' }}">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $card['label'] }}</div>
                    <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ number_format($card['count']) }}</div>
                </a>
            @endforeach
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-4">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="flex flex-col lg:flex-row flex-wrap gap-3 lg:items-end">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}"
                           placeholder="Email, name, order #, reference…"
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Method</label>
                    <select name="gateway" class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <option value="">All</option>
                        <option value="stripe" {{ request('gateway') === 'stripe' ? 'selected' : '' }}>Stripe</option>
                        <option value="paypal" {{ request('gateway') === 'paypal' ? 'selected' : '' }}>PayPal</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-gray-900 dark:bg-indigo-600 rounded-lg hover:bg-gray-800 dark:hover:bg-indigo-700">Apply</button>
                    <a href="{{ route('admin.payments.index', ['type' => $type]) }}" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600">Reset</a>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Payments</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $orders->total() }} items</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Order</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($orders as $order)
                            @php
                                $ps = $order->payment_status;
                                $statusLabel = match ($ps) {
                                    'paid' => 'Succeeded',
                                    'pending' => 'Incomplete',
                                    'failed' => 'Failed',
                                    'refunded' => 'Refunded',
                                    default => ucfirst((string) $ps),
                                };
                                $badge = match ($ps) {
                                    'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                    'pending' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                    'failed' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
                                    'refunded' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 cursor-pointer"
                                onclick="window.location='{{ route('admin.payments.order', $order) }}'">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ number_format((float) $order->total_amount, 2) }} {{ $currency }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-800 dark:text-gray-200">{{ $order->paymentMethodLabel() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" title="{{ $order->paymentActivityDescription() }}">{{ $order->paymentActivityDescription() }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $order->payerEmail() ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $order->payerDisplayName() }}</div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $order->created_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span class="text-indigo-600 dark:text-indigo-400 font-medium">#{{ $order->publicOrderNumberDigits() }}</span>
                                    @if($order->package_id)
                                        <span class="ml-1 text-xs text-amber-600 dark:text-amber-400">Pkg</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">No payments match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">{{ $orders->links() }}</div>
            @endif
        </div>

        @if($openMobileCheckouts->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Open Stripe mobile checkouts</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Payment intents not yet completed (not converted to an order)</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Intent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($openMobileCheckouts as $c)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/30 cursor-pointer"
                                    onclick="window.location='{{ route('admin.payments.mobile-checkout', $c) }}'">
                                    <td class="px-4 py-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ number_format((float) $c->total_amount, 2) }} {{ strtoupper($c->currency) }}</td>
                                    <td class="px-4 py-2"><span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">Incomplete</span></td>
                                    <td class="px-4 py-2 text-sm text-gray-600 dark:text-gray-300">{{ $c->user?->email ?? '—' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-500">{{ $c->updated_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}</td>
                                    <td class="px-4 py-2 text-xs font-mono text-gray-500 truncate max-w-[180px]">{{ $c->stripe_payment_intent_id ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>
</x-admin-layout>
