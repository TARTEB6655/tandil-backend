<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">Loyalty customers</h1>
        </div>

        <form method="GET" action="{{ route('admin.loyalty.customers') }}" class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="search" name="search" value="{{ $search }}" placeholder="Search name, email, or phone"
                   class="w-full rounded-xl ly-bg-beige border-0 py-3 pl-10 pr-4 text-sm focus:ring-[#1B4332]">
        </form>

        <div class="rounded-2xl ly-bg-beige p-5">
            <p class="text-base font-semibold text-gray-900">Track your most engaged customers</p>
            <p class="mt-1 text-sm text-gray-600">Search balances fast, spot repeat redeemers, and open any customer ledger in one tap.</p>
            <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-white px-3 py-3 text-center text-sm">
                <div><p class="font-bold ly-green">{{ $summary['visible'] }}</p><p class="text-xs text-gray-500">Visible</p></div>
                <div><p class="font-bold ly-green">{{ $summary['points_pool'] }}</p><p class="text-xs text-gray-500">Points pool</p></div>
                <div><p class="font-bold ly-green">{{ $summary['holders'] }}</p><p class="text-xs text-gray-500">Holders</p></div>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($customers as $c)
                <a href="{{ route('admin.loyalty.customers.show', $c['id']) }}"
                   class="flex items-center justify-between rounded-2xl ly-bg-beige px-4 py-3 transition hover:bg-[#ebe8de]">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white text-gray-500">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $c['name'] }}</p>
                            <p class="truncate text-sm text-gray-600">{{ $c['email'] }}</p>
                            <p class="text-xs text-gray-500">{{ $c['city'] }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-bold ly-green">{{ $c['points'] }}</p>
                        <p class="text-xs text-gray-500">pts</p>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">No customers found.</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
