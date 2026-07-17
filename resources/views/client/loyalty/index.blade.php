<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Loyalty Points</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Your rewards. Same as API GET /api/client/loyalty.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 sm:p-8 mb-6">
        <div class="flex items-center gap-4">
            <div class="h-14 w-14 rounded-full bg-amber-100 flex items-center justify-center">
                <span class="text-2xl font-bold text-amber-700">{{ $balance }}</span>
            </div>
            <div>
                <p class="text-sm font-medium text-gray-900">Points balance</p>
                <p class="text-sm text-gray-500">Redeem rewards below when you have enough points.</p>
            </div>
        </div>
    </div>

    <div class="space-y-6">
        <section>
            <h2 class="text-base font-medium text-gray-900 mb-3">Available Rewards</h2>
            <div class="space-y-3">
                @forelse ($availableRewards as $reward)
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $reward['title'] }}</p>
                                @if (!empty($reward['description']))
                                    <p class="mt-1 text-sm text-gray-500">{{ $reward['description'] }}</p>
                                @endif
                                <p class="mt-2 text-sm font-medium text-emerald-600">{{ $reward['points_required'] }} points</p>
                            </div>
                            <span class="text-xs sm:text-sm {{ $reward['can_redeem'] ? 'text-emerald-600' : 'text-gray-400' }}">
                                {{ $reward['status_label'] }}
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
