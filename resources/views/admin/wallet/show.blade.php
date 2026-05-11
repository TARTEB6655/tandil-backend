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

            {{-- flex-nowrap + equal flex children: always one row (no vertical stack) --}}
            <div class="flex w-full min-w-0 flex-nowrap items-stretch gap-2 sm:gap-3">
                <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-indigo-200/90 bg-gradient-to-br from-indigo-200/90 via-indigo-50 to-violet-100 p-3 shadow-md ring-1 ring-indigo-200/70 dark:border-indigo-700/70 dark:from-indigo-900/90 dark:via-gray-900 dark:to-violet-900/50 dark:ring-indigo-800/50 sm:min-h-0 sm:p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-indigo-700 dark:text-indigo-300 sm:text-xs">Balance</p>
                    <p class="mt-1.5 truncate text-lg font-bold tabular-nums text-indigo-950 dark:text-indigo-50 sm:text-xl">AED {{ number_format((float) $user->wallet_balance, 2) }}</p>
                    <p class="mt-2 line-clamp-2 text-[10px] leading-snug text-indigo-900/75 dark:text-indigo-200/80 sm:text-xs">In-app total</p>
                </div>
                <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-emerald-200/90 bg-gradient-to-br from-emerald-200/85 via-emerald-50 to-teal-100 p-3 shadow-md ring-1 ring-emerald-200/70 dark:border-emerald-800/50 dark:from-emerald-900/80 dark:via-gray-900 dark:to-teal-900/45 dark:ring-emerald-800/45 sm:min-h-0 sm:p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-300 sm:text-xs">First credit</p>
                    <p class="mt-1.5 text-xs font-bold tabular-nums leading-tight text-emerald-950 dark:text-emerald-50 sm:text-sm">
                        @if($firstWalletCreditAt)
                            <span class="block truncate">{{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('M d, Y') }}</span>
                            <span class="block text-[10px] font-semibold text-emerald-800 dark:text-emerald-200/90 sm:text-xs">{{ \Carbon\Carbon::parse($firstWalletCreditAt)->format('h:i A') }}</span>
                        @else
                            <span class="text-emerald-800/70 dark:text-emerald-300/70">—</span>
                        @endif
                    </p>
                    <p class="mt-2 line-clamp-2 text-[10px] leading-snug text-emerald-900/80 dark:text-emerald-100/75 sm:text-xs">Activity start</p>
                </div>
                <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-amber-200/90 bg-gradient-to-br from-amber-200/80 via-amber-50 to-orange-100 p-3 shadow-md ring-1 ring-amber-200/70 dark:border-amber-800/50 dark:from-amber-900/75 dark:via-gray-900 dark:to-orange-900/45 dark:ring-amber-800/45 sm:min-h-0 sm:p-4">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:text-amber-200 sm:text-xs">Next expiry</p>
                    <p class="mt-1.5 text-xs font-bold tabular-nums leading-tight text-amber-950 dark:text-amber-50 sm:text-sm">
                        @if($nextActiveCreditExpiresAt)
                            <span class="block truncate">{{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('M d, Y') }}</span>
                            <span class="block text-[10px] font-semibold text-amber-900 dark:text-amber-100/90 sm:text-xs">{{ \Carbon\Carbon::parse($nextActiveCreditExpiresAt)->format('h:i A') }}</span>
                        @else
                            <span class="text-[11px] text-amber-900/75 dark:text-amber-200/75">None</span>
                        @endif
                    </p>
                    <p class="mt-2 line-clamp-2 text-[10px] leading-snug text-amber-950/90 dark:text-amber-100/75 sm:text-xs">Active lines</p>
                </div>
            </div>
            <div class="rounded-lg border border-slate-200 bg-slate-50/90 px-4 py-3 text-xs leading-relaxed text-slate-700 dark:border-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                <span class="font-semibold text-slate-800 dark:text-slate-200">Policy note:</span>
                each credit typically expires after <strong>{{ (int) $walletValidityMonths }} months</strong> from its credit date unless spent sooner — see refund / payment settings.
            </div>
        </div>

        <div class="flex w-full min-w-0 flex-nowrap items-stretch gap-2 sm:gap-3">
            <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-sky-200/90 bg-gradient-to-br from-sky-200/75 via-sky-50 to-slate-100 p-3 shadow-md ring-1 ring-sky-200/70 dark:border-sky-800/50 dark:from-sky-900/70 dark:via-gray-900 dark:to-slate-900/60 dark:ring-sky-800/40 sm:min-h-0 sm:p-4">
                <p class="text-[10px] font-bold uppercase tracking-wide text-sky-800 dark:text-sky-300 sm:text-xs">Paid orders</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-slate-100 sm:text-xl">{{ $orderStats['paid_orders_count'] }}</p>
                <p class="mt-1.5 truncate text-[10px] text-sky-900/80 dark:text-sky-200/75 sm:text-xs">AED {{ number_format($orderStats['paid_orders_total_aed'], 2) }}</p>
            </div>
            <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-rose-200/90 bg-gradient-to-br from-rose-200/70 via-rose-50 to-orange-100 p-3 shadow-md ring-1 ring-rose-200/70 dark:border-rose-900/45 dark:from-rose-900/65 dark:via-gray-900 dark:to-orange-900/40 dark:ring-rose-800/35 sm:min-h-0 sm:p-4">
                <p class="text-[10px] font-bold uppercase tracking-wide text-rose-800 dark:text-rose-300 sm:text-xs">Cancelled</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-slate-100 sm:text-xl">{{ $orderStats['cancelled_orders_count'] }}</p>
                <p class="mt-1.5 truncate text-[10px] text-rose-900/80 dark:text-rose-200/75 sm:text-xs">AED {{ number_format($orderStats['cancelled_orders_total_aed'], 2) }}</p>
            </div>
            <div class="flex min-h-[5.5rem] min-w-0 flex-1 basis-0 flex-col overflow-hidden rounded-xl border border-violet-200/90 bg-gradient-to-br from-violet-200/75 via-violet-50 to-fuchsia-100 p-3 shadow-md ring-1 ring-violet-200/70 dark:border-violet-800/50 dark:from-violet-900/70 dark:via-gray-900 dark:to-fuchsia-900/40 dark:ring-violet-800/40 sm:min-h-0 sm:p-4">
                <p class="text-[10px] font-bold uppercase tracking-wide text-violet-800 dark:text-violet-300 sm:text-xs">Credit rows</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-slate-900 dark:text-slate-100 sm:text-xl">{{ $walletCreditRows }}</p>
                <p class="mt-1.5 line-clamp-2 text-[10px] text-violet-900/80 dark:text-violet-200/75 sm:text-xs">All-time lines</p>
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
