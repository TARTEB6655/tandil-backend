<x-admin-layout>
    @include('admin.loyalty._theme')
    @php
        $health = $report['health'];
        $summary = $report['summary'];
        $f = $report['filters'];
        $scope = old('customer_scope', $filters['customer_scope'] ?? 'all');
        $period = old('period', $filters['period'] ?? 'month');
        $selectedIds = old('specific_customer_ids', $filters['specific_customer_ids'] ?? []);
    @endphp

    <div class="space-y-6" x-data="{ scope: @js($scope), period: @js($period) }">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Reports & export</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Export format: <strong>CSV</strong> (same as mobile app)</p>
                </div>
            </div>
            <a href="{{ route('admin.loyalty.export', request()->query()) }}"
               class="ly-btn inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-sm">
                Export CSV
            </a>
        </div>

        <div class="rounded-xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-600 to-indigo-700 p-5 text-white shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-lg font-semibold">Read the health of your loyalty program</p>
                    <p class="mt-1 text-sm text-indigo-100">Overview of loyalty activity. Export CSV for offline analysis.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                    {{ $health['status_label'] }}
                </span>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-3 rounded-xl bg-white/10 px-3 py-3 text-center text-sm">
                <div><p class="text-xl font-bold">{{ number_format($health['outstanding']) }}</p><p class="text-xs text-indigo-100">Outstanding</p></div>
                <div><p class="text-xl font-bold">{{ number_format($health['redeemed']) }}</p><p class="text-xs text-indigo-100">Redeemed</p></div>
                <div><p class="text-xl font-bold">{{ number_format($health['campaigns']) }}</p><p class="text-xs text-indigo-100">Campaigns</p></div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.loyalty.reports') }}" class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Customer scope</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <button type="button" @click="scope='all'" :class="scope==='all' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700'" class="rounded-xl px-3 py-2 text-sm font-medium">All customers</button>
                    <button type="button" @click="scope='specific'" :class="scope==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700'" class="rounded-xl px-3 py-2 text-sm font-medium">Specific customer</button>
                </div>
                <input type="hidden" name="customer_scope" :value="scope">
                <div x-show="scope==='specific'" x-cloak class="mt-3 max-h-40 space-y-1 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/40">
                    @foreach(($clients ?? []) as $client)
                        <label class="flex items-center gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="specific_customer_ids[]" value="{{ $client->id }}" class="rounded text-indigo-600 focus:ring-indigo-500"
                                   {{ in_array($client->id, (array) $selectedIds, false) || in_array((string) $client->id, array_map('strval', (array) $selectedIds), true) ? 'checked' : '' }}>
                            {{ $client->name }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Period</p>
                <div class="mt-2 grid grid-cols-3 gap-2">
                    <button type="button" @click="period='week'" :class="period==='week' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700'" class="rounded-xl px-3 py-2 text-sm font-medium">Week</button>
                    <button type="button" @click="period='month'" :class="period==='month' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700'" class="rounded-xl px-3 py-2 text-sm font-medium">Month</button>
                    <button type="button" @click="period='specific'" :class="period==='specific' ? 'ly-chip-on border' : 'border border-gray-200 bg-white text-gray-600 dark:border-gray-600 dark:bg-gray-700'" class="rounded-xl px-3 py-2 text-sm font-medium">Specific date</button>
                </div>
                <input type="hidden" name="period" :value="period">
                <div x-show="period==='specific'" x-cloak class="mt-3 grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500">From</label>
                        <input type="date" name="date_from" value="{{ $filters['date_from'] ?? $f['date_from'] }}" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500">To</label>
                        <input type="date" name="date_to" value="{{ $filters['date_to'] ?? $f['date_to'] }}" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    </div>
                </div>
            </div>

            <button type="submit" class="ly-btn w-full rounded-lg px-4 py-3 text-sm font-semibold shadow-sm">Apply filters</button>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ number_format($summary['customers_with_points']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Customers with points</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($summary['points_outstanding']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Points outstanding</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/30">
                <p class="text-2xl font-bold text-teal-900 dark:text-teal-100">{{ number_format($summary['points_earned']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-teal-700 dark:text-teal-300">Points earned</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-800 dark:bg-rose-950/30">
                <p class="text-2xl font-bold text-rose-900 dark:text-rose-100">{{ number_format($summary['points_redeemed']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-rose-700 dark:text-rose-300">Points redeemed</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-950/30">
                <p class="text-2xl font-bold text-violet-900 dark:text-violet-100">{{ number_format($summary['rewards_redeemed']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-violet-700 dark:text-violet-300">Rewards redeemed</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/30">
                <p class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ number_format($summary['active_campaigns']) }}</p>
                <p class="mt-1 text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Active campaigns</p>
            </div>
        </div>

        <p class="text-xs text-gray-500 dark:text-gray-400">Period: {{ $f['date_from'] }} → {{ $f['date_to'] }} · Scope: {{ $f['customer_scope'] === 'specific' ? 'Specific customer' : 'All customers' }}</p>
    </div>
</x-admin-layout>
