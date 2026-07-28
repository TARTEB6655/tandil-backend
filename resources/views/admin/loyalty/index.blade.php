<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="mx-auto max-w-4xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Loyalty Points</h1>
                <p class="mt-1 text-sm text-gray-500">Configure earning, rewards, campaigns, and customer points.</p>
            </div>
            <a href="{{ route('admin.loyalty.export') }}"
               class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Export report
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        {{-- Control center hero --}}
        <div class="rounded-2xl ly-bg-green p-5 text-white shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-white/15">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">Loyalty control center</h2>
                        <p class="mt-1 text-sm text-white/80">Configure earning, rewards, campaigns, and customer points.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold">
                    <span class="h-2 w-2 rounded-full {{ $dashboard['loyalty_system_enabled'] ? 'bg-emerald-300' : 'bg-gray-300' }}"></span>
                    {{ $dashboard['status'] }}
                </span>
            </div>
        </div>

        {{-- Master toggle --}}
        <div class="flex items-center justify-between rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div>
                <p class="text-sm font-semibold text-gray-900">Loyalty system</p>
                <p class="mt-0.5 text-sm text-gray-500">{{ $dashboard['status_label'] }}</p>
            </div>
            <form method="POST" action="{{ route('admin.loyalty.toggle') }}">
                @csrf
                <input type="hidden" name="loyalty_system_enabled" value="{{ $dashboard['loyalty_system_enabled'] ? '0' : '1' }}">
                <button type="submit" class="relative inline-flex h-7 w-12 items-center rounded-full transition {{ $dashboard['loyalty_system_enabled'] ? 'ly-toggle-on' : 'bg-gray-300' }}" aria-label="Toggle loyalty">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition {{ $dashboard['loyalty_system_enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                </button>
            </form>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 text-center shadow-sm">
                <p class="text-2xl font-bold ly-green">{{ $dashboard['points_per_aed'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Points / AED</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 text-center shadow-sm">
                <p class="text-2xl font-bold ly-green">{{ $dashboard['activities'] }}</p>
                <p class="mt-1 text-xs text-gray-500">Activities</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-4 text-center shadow-sm">
                <p class="text-2xl font-bold ly-green">{{ $dashboard['expiry_months'] ?? '—' }}</p>
                <p class="mt-1 text-xs text-gray-500">Expiry (mo)</p>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Manage</h3>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @php
                    $manage = [
                        ['title' => 'System settings', 'description' => 'Enable loyalty, points per AED, eligibility & expiry.', 'url' => route('admin.loyalty.settings')],
                        ['title' => 'Rewards', 'description' => 'Create, edit, enable/disable reward catalog.', 'url' => route('admin.loyalty.rewards')],
                        ['title' => 'Customers', 'description' => 'Search balances and open any customer ledger.', 'url' => route('admin.loyalty.customers')],
                        ['title' => 'Campaigns', 'description' => 'Schedule multipliers and seasonal point boosts.', 'url' => route('admin.loyalty.campaigns')],
                    ];
                @endphp
                @foreach($manage as $item)
                    <a href="{{ $item['url'] }}"
                       class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-[#1B4332]/40">
                        <p class="text-sm font-semibold text-gray-900">{{ $item['title'] }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ $item['description'] }}</p>
                        <p class="mt-3 text-sm font-semibold ly-green">Open →</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-admin-layout>
