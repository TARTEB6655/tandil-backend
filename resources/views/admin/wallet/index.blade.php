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

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
             x-data="{
                q: @js($q),
                perPage: '{{ (int) $perPage }}',
                listUrl: @js(route('admin.wallet.index')),
                buildHref() {
                    const u = new URL(this.listUrl, window.location.origin);
                    const trimmed = String(this.q ?? '').trim();
                    if (trimmed) u.searchParams.set('q', trimmed);
                    else u.searchParams.delete('q');
                    u.searchParams.set('per_page', String(this.perPage));
                    u.searchParams.delete('page');
                    return u.pathname + u.search;
                },
                go() {
                    const next = this.buildHref();
                    const cur = window.location.pathname + window.location.search;
                    if (next === cur) return;
                    window.location.href = next;
                },
                clearSearch() {
                    this.q = '';
                    this.go();
                },
             }">
            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Search</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Type to filter clients; results refresh automatically after a short pause while typing.</p>
            </div>
            <div class="p-4">
                <div class="flex flex-nowrap items-center gap-4 overflow-x-auto pb-1 min-w-0">
                    <div class="relative min-w-0 flex-[1_1_16rem] max-w-xl shrink">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input
                            id="wallet-q"
                            type="search"
                            autocomplete="off"
                            x-model="q"
                            placeholder="Name or email"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 pl-10 pr-10 text-sm text-gray-900 placeholder:text-xs placeholder:text-gray-400 focus:border-gray-300 focus:bg-white focus:outline-none focus:ring-1 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:bg-gray-800 dark:focus:ring-gray-600"
                            @input.debounce.320ms="go()"
                            @keydown.enter.prevent="go()"
                        />
                        <button
                            type="button"
                            x-show="String(q || '').trim().length > 0"
                            x-cloak
                            @click="clearSearch()"
                            class="absolute inset-y-0 right-0 flex items-center justify-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                            aria-label="Clear search"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <label for="wallet-per-page" class="shrink-0 text-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400">Per page</label>
                        <select
                            id="wallet-per-page"
                            x-model="perPage"
                            @change="go()"
                            class="h-11 w-[4.5rem] shrink-0 rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-900 focus:border-gray-300 focus:bg-white focus:outline-none focus:ring-1 focus:ring-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 dark:focus:bg-gray-800 dark:focus:ring-gray-600"
                        >
                            @foreach([20, 50, 100] as $size)
                                <option value="{{ $size }}">{{ $size }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
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
