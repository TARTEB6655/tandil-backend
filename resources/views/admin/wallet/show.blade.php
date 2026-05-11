<x-admin-layout>
    <div class="space-y-6">
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <a href="{{ route('admin.wallet.index', request()->only(['q', 'per_page'])) }}"
                       class="inline-flex w-fit items-center gap-2 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to wallet
                    </a>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Client wallet &amp; history</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->name }} — {{ $user->email }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="flex flex-col rounded-xl border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/50 dark:bg-indigo-950/40">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800 dark:text-indigo-200">Wallet balance</p>
                    <p class="mt-3 text-2xl font-bold tabular-nums tracking-tight text-indigo-950 dark:text-indigo-50 sm:text-3xl">AED {{ number_format((float) $user->wallet_balance, 2) }}</p>
                    <p class="mt-auto pt-4 text-xs leading-relaxed text-indigo-900/80 dark:text-indigo-200/80">Current in-app wallet total for this client.</p>
                </div>
                <div class="flex flex-col rounded-xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/35">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">First wallet credit</p>
                    <p class="mt-3 text-lg font-bold tabular-nums text-emerald-950 dark:text-emerald-50 sm:text-xl">
                        @if($firstWalletCreditAt)
                            {{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('M d, Y') }}
                            <span class="mt-0.5 block text-sm font-semibold text-emerald-900/90 dark:text-emerald-100/90">{{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('h:i A') }}</span>
                        @else
                            <span class="text-base font-semibold text-emerald-800/70 dark:text-emerald-200/70">—</span>
                        @endif
                    </p>
                    <p class="mt-auto pt-4 text-xs leading-relaxed text-emerald-900/85 dark:text-emerald-100/80">When refund credit activity started (earliest credit issued).</p>
                </div>
                <div class="flex flex-col rounded-xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-900/40 dark:bg-amber-950/35">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-900 dark:text-amber-200">Next active expiry</p>
                    <p class="mt-3 text-lg font-bold tabular-nums text-amber-950 dark:text-amber-50 sm:text-xl">
                        @if($nextActiveCreditExpiresAt)
                            {{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('M d, Y') }}
                            <span class="mt-0.5 block text-sm font-semibold text-amber-900/90 dark:text-amber-100/90">{{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('h:i A') }}</span>
                        @else
                            <span class="text-base font-semibold text-amber-900/70 dark:text-amber-200/70">None scheduled</span>
                        @endif
                    </p>
                    <p class="mt-auto pt-4 text-xs leading-relaxed text-amber-950/90 dark:text-amber-100/80">Soonest expiry among <strong>active</strong> credit lines that have a date. Lines can expire separately.</p>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/90 px-4 py-3 text-xs leading-relaxed text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <span class="font-semibold text-slate-800 dark:text-slate-200">Policy note:</span>
                each credit typically expires after <strong>{{ (int) $walletValidityMonths }} months</strong> from its credit date unless spent sooner — see refund / payment settings.
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
                <p class="text-xs text-gray-600 dark:text-gray-400">Refund / wallet credit lines recorded for this client.</p>
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
    </div>
</x-admin-layout>
