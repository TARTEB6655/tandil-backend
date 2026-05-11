<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Wallet Monitoring</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monitor refund credits, expiry, and company forfeiture exposure.</p>
            </div>
            <a href="{{ route('admin.payments.settings') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                Refund policy settings
            </a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">Total wallet balance</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900">AED {{ number_format((float) $summary['total_wallet_balance'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Active liability</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900">AED {{ number_format((float) $summary['active_wallet_liability'], 2) }}</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-rose-700">Forfeited total</p>
                <p class="mt-1 text-2xl font-bold text-rose-900">AED {{ number_format((float) $summary['forfeited_total'], 2) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Filters</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Refine wallet ledger by user and status.</p>
            </div>
            <form method="GET" action="{{ route('admin.wallet.index') }}" class="p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                    <div class="min-w-0 sm:col-span-2 lg:col-span-5">
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400" for="wallet-q">Search user</label>
                        <input id="wallet-q" type="text" name="q" value="{{ $q }}" placeholder="Name or email"
                               class="h-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                    </div>
                    <div class="w-full sm:w-auto lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400" for="wallet-status">Status</label>
                        <select id="wallet-status" name="status" class="h-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                            <option value="">All</option>
                            @foreach(['active', 'forfeited', 'used', 'expired'] as $st)
                                <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-auto lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400" for="wallet-per-page">Per page</label>
                        <select id="wallet-per-page" name="per_page" class="h-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                            @foreach([20, 50, 100] as $size)
                                <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-3 lg:justify-end">
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                            Apply
                        </button>
                        <a href="{{ route('admin.wallet.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        @if($q !== '' && $focusUser && $userInsight)
            <div class="rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-5 shadow-sm dark:border-indigo-900/40 dark:from-indigo-950/40 dark:to-gray-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Selected client</p>
                        <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $focusUser->name }}</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $focusUser->email }}</p>
                        @if($userMatchCount > 1)
                            <p class="mt-2 text-xs text-amber-800 dark:text-amber-200/90">
                                {{ $userMatchCount }} users match this search — stats below are for this client only (first match). Narrow the search (e.g. full email) to target one person.
                            </p>
                        @endif
                    </div>
                    <div class="text-left lg:text-right">
                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Wallet balance</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums text-indigo-900 dark:text-indigo-100">AED {{ number_format($userInsight['wallet_balance'], 2) }}</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Active credit lines: AED {{ number_format($userInsight['active_credits_aed'], 2) }}</p>
                    </div>
                </div>
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-gray-200 bg-white/80 p-3 dark:border-gray-700 dark:bg-gray-800/80">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Paid shop orders</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $userInsight['paid_orders_count'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Total AED {{ number_format($userInsight['paid_orders_total_aed'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white/80 p-3 dark:border-gray-700 dark:bg-gray-800/80">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cancelled shop orders</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $userInsight['cancelled_orders_count'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Order total AED {{ number_format($userInsight['cancelled_orders_total_aed'], 2) }}</p>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white/80 p-3 dark:border-gray-700 dark:bg-gray-800/80">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Wallet credit rows (all time)</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ $userInsight['wallet_credit_rows'] }}</p>
                        <p class="text-xs text-gray-600 dark:text-gray-400">Ledger lines for this client (see table below).</p>
                    </div>
                </div>
            </div>
        @elseif($q !== '' && ! $focusUser)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                No user matched “{{ $q }}”. Try another name or email.
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Wallet credit ledger</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $credits->total() }} entries</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/30">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">User</th>
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
                                <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-200">
                                    <div class="font-medium">{{ $credit->user?->name ?: 'N/A' }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $credit->user?->email ?: '-' }}</div>
                                </td>
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
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $credit->order_id ? '#' . $credit->order_id : '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ optional($credit->credited_at)->format('M d, Y h:i A') ?: '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ optional($credit->expires_at)->format('M d, Y h:i A') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No wallet credits found.</td>
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

