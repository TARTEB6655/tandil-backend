<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Wallet Monitoring</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Browse clients and open a user to see shop orders, cancellations, and timestamps.</p>
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
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search clients by name or email.</p>
            </div>
            <form method="GET" action="{{ route('admin.wallet.index') }}" class="p-4">
                {{-- Single horizontal toolbar: label + control on one line; scroll on narrow viewports --}}
                <div class="flex flex-nowrap items-center gap-3 overflow-x-auto pb-1 min-w-0">
                    <div class="flex min-w-0 flex-[1_1_14rem] max-w-xl items-center gap-2 shrink-0">
                        <label for="wallet-q" class="shrink-0 text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Search</label>
                        <input id="wallet-q" type="text" name="q" value="{{ $q }}" placeholder="Name or email"
                               class="h-10 min-w-[10rem] flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label for="wallet-per-page" class="shrink-0 text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Per page</label>
                        <select id="wallet-per-page" name="per_page" class="h-10 w-[4.5rem] shrink-0 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm">
                            @foreach([20, 50, 100] as $size)
                                <option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ml-auto flex shrink-0 items-center gap-2">
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 whitespace-nowrap">
                            Apply
                        </button>
                        <a href="{{ route('admin.wallet.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 whitespace-nowrap">
                            Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        @if($q !== '' && $users->isEmpty())
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                No client matched “{{ $q }}”. Try another name or email.
            </div>
        @endif

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Clients</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $users->total() }} users</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/30">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Wallet balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($users as $u)
                            <tr class="cursor-pointer hover:bg-indigo-50/60 dark:hover:bg-indigo-950/20 transition-colors"
                                role="link"
                                tabindex="0"
                                onclick="window.location.href='{{ route('admin.wallet.user', $u) }}'"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href='{{ route('admin.wallet.user', $u) }}';}">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $u->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $u->email }}</td>
                                <td class="px-4 py-3 text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">AED {{ number_format((float) $u->wallet_balance, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No clients to show.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
