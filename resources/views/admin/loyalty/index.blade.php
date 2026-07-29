<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Loyalty Points</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Configure earning, rewards, campaigns, and customer points — same data as the admin API.</p>
            </div>
            <a href="{{ route('admin.loyalty.reports') }}"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                Reports & export
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">{{ session('success') }}</div>
        @endif

        <div class="rounded-xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-600 to-indigo-700 p-5 text-white shadow-md dark:border-indigo-700">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">Loyalty control center</h2>
                        <p class="mt-1 text-sm text-indigo-100">{{ $dashboard['status_label'] }}</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                    <span class="h-2 w-2 rounded-full {{ $dashboard['loyalty_system_enabled'] ? 'bg-emerald-300' : 'bg-gray-300' }}"></span>
                    {{ $dashboard['status'] }}
                </span>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border-2 border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-600 dark:bg-gray-800">
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Loyalty system</p>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Master switch — matches API <code class="text-xs">loyalty_system_enabled</code></p>
            </div>
            <form method="POST" action="{{ route('admin.loyalty.toggle') }}">
                @csrf
                <input type="hidden" name="loyalty_system_enabled" value="{{ $dashboard['loyalty_system_enabled'] ? '0' : '1' }}">
                <button type="submit" class="relative inline-flex h-7 w-12 items-center rounded-full transition {{ $dashboard['loyalty_system_enabled'] ? 'ly-toggle-on' : 'bg-gray-300' }}" aria-label="Toggle loyalty">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $dashboard['loyalty_system_enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Points / AED</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ $dashboard['points_per_aed'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Activities</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ $dashboard['activities'] }}</p>
            </div>
            <div class="rounded-xl border border-teal-200 bg-teal-50 p-4 dark:border-teal-800 dark:bg-teal-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">Expiry (months)</p>
                <p class="mt-1 text-2xl font-bold text-teal-900 dark:text-teal-100">{{ $dashboard['expiry_months'] ?? '—' }}</p>
            </div>
        </div>

        <div>
            <h2 class="mb-3 text-lg font-bold text-gray-900 dark:text-gray-50 border-l-4 border-indigo-500 pl-3">Manage</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @php
                    $manage = [
                        ['title' => 'System settings', 'description' => 'Enable loyalty, points per AED, eligibility, cities & targeting.', 'url' => route('admin.loyalty.settings'), 'tone' => 'indigo'],
                        ['title' => 'Rewards', 'description' => 'Create, edit, enable/disable reward catalog.', 'url' => route('admin.loyalty.rewards'), 'tone' => 'emerald'],
                        ['title' => 'Customers', 'description' => 'Search balances and open any customer ledger.', 'url' => route('admin.loyalty.customers'), 'tone' => 'teal'],
                        ['title' => 'Campaigns', 'description' => 'Schedule multipliers and seasonal point boosts.', 'url' => route('admin.loyalty.campaigns'), 'tone' => 'violet'],
                        ['title' => 'Reports & export', 'description' => 'Filter by customer/period and export PDF.', 'url' => route('admin.loyalty.reports'), 'tone' => 'amber'],
                    ];
                @endphp
                @foreach($manage as $item)
                    <a href="{{ $item['url'] }}"
                       class="group flex items-center gap-4 rounded-xl border-2 border-gray-200 bg-white p-5 shadow-md transition hover:border-indigo-400 hover:shadow-lg dark:border-gray-600 dark:bg-gray-800 dark:hover:border-indigo-500">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $item['title'] }}</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item['description'] }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
