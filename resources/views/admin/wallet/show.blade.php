<x-admin-layout>
    <div class="space-y-6">
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <a href="{{ route('admin.wallet.index', request()->only(['q', 'per_page'])) }}"
                       class="inline-flex w-fit min-h-[2.75rem] items-center gap-2.5 rounded-lg border border-gray-300 bg-white pl-4 pr-5 py-2.5 text-sm font-medium text-gray-800 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700">
                        <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back to wallet
                    </a>
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Client wallet &amp; history</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $user->name }} — {{ $user->email }}</p>
                </div>
            </div>

            {{-- Same row pattern as admin dashboard catalog cards: icon + title + value + subtitle + chevron --}}
            <div class="flex w-full min-w-0 flex-nowrap items-stretch gap-3 sm:gap-4">
                <div class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-indigo-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-indigo-500 sm:gap-4 sm:p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition-transform group-hover:scale-105 dark:bg-indigo-900/30 dark:text-indigo-400 sm:h-12 sm:w-12">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h2a2 2 0 110 4h-2m0-4v4"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Wallet balance</p>
                        <p class="truncate text-xl font-bold tabular-nums text-indigo-700 dark:text-indigo-300 sm:text-2xl">AED {{ number_format((float) $user->wallet_balance, 2) }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">In-app total for this client</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-indigo-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-teal-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-teal-500 sm:gap-4 sm:p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-50 text-teal-600 transition-transform group-hover:scale-105 dark:bg-teal-900/30 dark:text-teal-400 sm:h-12 sm:w-12">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">First wallet credit</p>
                        @if($firstWalletCreditAt)
                            <p class="truncate text-xl font-bold text-teal-700 dark:text-teal-300 sm:text-2xl">{{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('h:i A') }} · earliest refund credit</p>
                        @else
                            <p class="text-xl font-bold text-gray-400 dark:text-gray-500 sm:text-2xl">—</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">No credits yet</p>
                        @endif
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-teal-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
                <div class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-amber-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-amber-500 sm:gap-4 sm:p-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 transition-transform group-hover:scale-105 dark:bg-amber-900/30 dark:text-amber-400 sm:h-12 sm:w-12">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Next active expiry</p>
                        @if($nextActiveCreditExpiresAt)
                            <p class="truncate text-xl font-bold text-amber-700 dark:text-amber-300 sm:text-2xl">{{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('M d, Y') }}</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('h:i A') }} · soonest among active lines</p>
                        @else
                            <p class="text-xl font-bold text-gray-400 dark:text-gray-500 sm:text-2xl">None</p>
                            <p class="text-xs text-gray-600 dark:text-gray-400">No upcoming expiry</p>
                        @endif
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-amber-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/90 px-4 py-3 text-xs leading-relaxed text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <span class="font-semibold text-slate-800 dark:text-slate-200">Policy note:</span>
                each credit typically expires after <strong>{{ (int) $walletValidityMonths }} months</strong> from its credit date unless spent sooner — see refund / payment settings.
            </div>
        </div>

        <div class="flex w-full min-w-0 flex-nowrap items-stretch gap-3 sm:gap-4">
            <a href="{{ route('admin.orders.index', ['search' => $user->email]) }}" class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-sky-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-sky-500 sm:gap-4 sm:p-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600 transition-transform group-hover:scale-105 dark:bg-sky-900/30 dark:text-sky-400 sm:h-12 sm:w-12">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Paid shop orders</p>
                    <p class="text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ $orderStats['paid_orders_count'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Total AED {{ number_format($orderStats['paid_orders_total_aed'], 2) }}</p>
                </div>
                <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-sky-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <a href="{{ route('admin.orders.cancelled', ['search' => $user->email]) }}" class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-rose-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-rose-500 sm:gap-4 sm:p-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600 transition-transform group-hover:scale-105 dark:bg-rose-900/30 dark:text-rose-400 sm:h-12 sm:w-12">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cancelled orders</p>
                    <p class="text-2xl font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ $orderStats['cancelled_orders_count'] }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Order total AED {{ number_format($orderStats['cancelled_orders_total_aed'], 2) }}</p>
                </div>
                <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-rose-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <div class="group flex min-w-0 flex-1 basis-0 items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-4 shadow-md transition-all duration-200 hover:border-violet-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-violet-500 sm:gap-4 sm:p-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 transition-transform group-hover:scale-105 dark:bg-violet-900/30 dark:text-violet-400 sm:h-12 sm:w-12">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Wallet credit rows</p>
                    <p class="text-2xl font-bold tabular-nums text-violet-700 dark:text-violet-300">{{ $walletCreditRows }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">All-time ledger lines for this client</p>
                </div>
                <svg class="h-5 w-5 shrink-0 text-gray-400 transition-colors group-hover:text-violet-500 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
