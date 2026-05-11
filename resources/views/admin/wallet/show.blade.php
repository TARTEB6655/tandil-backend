<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <a href="{{ route('admin.wallet.index', request()->only(['q', 'per_page'])) }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300">&larr; Back to wallet</a>
                <h1 class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">Client wallet &amp; history</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->name }} — {{ $user->email }}</p>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 dark:border-indigo-900/50 dark:bg-indigo-950/40">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Wallet balance</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-indigo-900 dark:text-indigo-100">AED {{ number_format((float) $user->wallet_balance, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Paid shop orders</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $orderStats['paid_orders_count'] }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Total AED {{ number_format($orderStats['paid_orders_total_aed'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cancelled shop orders</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $orderStats['cancelled_orders_count'] }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Order total AED {{ number_format($orderStats['cancelled_orders_total_aed'], 2) }}</p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Wallet credit rows (all time)</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $walletCreditRows }}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">Ledger lines for refund / wallet credits.</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Shop orders</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Newest first. Placed and last updated timestamps.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/30">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Placed</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Updated</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Payment</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($orders as $order)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-900/30">
                                <td class="px-4 py-3 text-sm">
                                    <a href="{{ route('admin.orders.show', $order) }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">#{{ $order->id }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $order->created_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $order->updated_at?->format('M d, Y h:i A') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $order->order_status ? ucfirst(str_replace('_', ' ', (string) $order->order_status)) : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-200">{{ $order->payment_status ? ucfirst((string) $order->payment_status) : '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">AED {{ number_format((float) $order->total_amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No orders for this client.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($orders->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Wallet credit ledger</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Refund credits linked to orders (when applicable).</p>
                </div>
                <form method="GET" action="{{ route('admin.wallet.user', $user) }}" class="flex flex-wrap items-end gap-2">
                    @if(request()->filled('orders_page'))
                        <input type="hidden" name="orders_page" value="{{ request('orders_page') }}">
                    @endif
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400" for="credit-status">Credit status</label>
                        <select id="credit-status" name="credit_status" class="h-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm min-w-[10rem]">
                            <option value="" {{ ($creditStatus ?? '') === '' ? 'selected' : '' }}>All</option>
                            @foreach(['active', 'forfeited', 'used', 'expired'] as $st)
                                <option value="{{ $st }}" {{ ($creditStatus ?? '') === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Apply
                    </button>
                    <a href="{{ route('admin.wallet.user', $user) }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                        Reset
                    </a>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/30">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Credited</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Expires</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($credits as $credit)
                            <tr>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">AED {{ number_format((float) $credit->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm">
                                    @php
                                        $badge = match((string) $credit->status) {
                                            'active' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
                                            'forfeited' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200',
                                            'used' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
                                            default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                                        };
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $badge }}">{{ ucfirst((string) $credit->status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @if($credit->order_id)
                                        <a href="{{ route('admin.orders.show', $credit->order_id) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">#{{ $credit->order_id }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ optional($credit->credited_at)->format('M d, Y h:i A') ?: '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ optional($credit->expires_at)->format('M d, Y h:i A') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No wallet credits for this filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($credits->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $credits->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
