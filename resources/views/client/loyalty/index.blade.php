<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Loyalty Points</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Same payload as API GET /api/client/loyalty.</p>
    </div>

    <div class="rounded-2xl bg-emerald-800 p-6 sm:p-8 mb-6 text-white shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-100">Your points</p>
        <p class="mt-2 text-3xl font-bold">{{ number_format($balance) }} points</p>
        @if(!empty($earnCaption))
            <p class="mt-2 text-sm text-emerald-100">{{ $earnCaption }}</p>
        @endif
        @if(!empty($summaryBadges))
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach($summaryBadges as $badge)
                    <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-medium">{{ $badge['label'] }}</span>
                @endforeach
            </div>
        @endif
    </div>

    @if(!empty($activeCampaigns))
        <section class="mb-6">
            <h2 class="text-base font-medium text-gray-900 mb-3">Active campaigns</h2>
            <div class="space-y-3">
                @foreach($activeCampaigns as $campaign)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">{{ $campaign['title'] }}</p>
                                <p class="mt-1 text-sm text-emerald-800">{{ $campaign['subtitle'] }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $campaign['date_range'] }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-700 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-white">{{ $campaign['boost_label'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="space-y-6">
        <section>
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-base font-medium text-gray-900">Available Rewards</h2>
                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-emerald-100 px-1.5 text-xs font-semibold text-emerald-800">{{ count($availableRewards) }}</span>
            </div>
            <div class="space-y-3">
                @forelse ($availableRewards as $reward)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $reward['title'] }}</p>
                                @if (!empty($reward['description']))
                                    <p class="mt-1 text-sm text-gray-500">{{ $reward['description'] }}</p>
                                @endif
                                <p class="mt-2 text-sm font-medium text-emerald-600">{{ $reward['points_label'] ?? ($reward['points_required'].' points') }}</p>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide {{ $reward['can_redeem'] ? 'bg-emerald-700 text-white' : 'bg-gray-100 text-gray-500' }}">
                                {{ $reward['badge_label'] ?? $reward['status_label'] }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No rewards available yet.</p>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="text-base font-medium text-gray-900 mb-3">Recent Transactions</h2>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
                @forelse ($recentTransactions as $transaction)
                    <div class="flex items-center justify-between gap-4 p-4 sm:p-5">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $transaction['type'] === 'earn' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $transaction['type'] === 'earn' ? '+' : '−' }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $transaction['title'] }}</p>
                                <p class="text-xs text-gray-500">{{ $transaction['date'] }}</p>
                            </div>
                        </div>
                        <span class="text-sm font-medium {{ $transaction['type'] === 'earn' ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ $transaction['points_display'] }}
                        </span>
                    </div>
                @empty
                    <p class="p-4 sm:p-5 text-sm text-gray-500">No transactions yet.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-client-layout>
