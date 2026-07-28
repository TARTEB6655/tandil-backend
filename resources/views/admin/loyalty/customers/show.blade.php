<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="mx-auto max-w-2xl space-y-5">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.customers') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">Customer points</h1>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl ly-bg-green p-5 text-white">
            <p class="text-lg font-semibold">{{ $points['name'] }}</p>
            <p class="mt-1 text-sm text-white/80">{{ $points['email'] }}</p>
            <p class="mt-1 text-sm text-white/70">{{ $points['city'] }}</p>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl ly-bg-beige px-3 py-4 text-center">
                <p class="text-2xl font-bold ly-green">{{ $points['balance'] }}</p>
                <p class="text-xs text-gray-500">Balance</p>
            </div>
            <div class="rounded-2xl ly-bg-beige px-3 py-4 text-center">
                <p class="text-2xl font-bold ly-green">{{ $points['earned'] }}</p>
                <p class="text-xs text-gray-500">Earned</p>
            </div>
            <div class="rounded-2xl ly-bg-beige px-3 py-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ $points['redeemed'] }}</p>
                <p class="text-xs text-gray-500">Redeemed</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.loyalty.customers.adjust', $customer->id) }}" class="space-y-3 rounded-2xl ly-bg-beige p-5">
            @csrf
            <div>
                <p class="text-sm font-semibold text-gray-900">Manual adjust</p>
                <p class="text-xs text-gray-500">Positive to credit, negative to deduct.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Amount (+/-)</label>
                <input type="number" name="amount" placeholder="50 or -20" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Reason</label>
                <input type="text" name="reason" placeholder="Optional note"
                       class="mt-1.5 w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-[#1B4332] focus:ring-[#1B4332]">
            </div>
            <button type="submit" class="ly-btn w-full rounded-xl px-4 py-3 text-sm font-semibold">Apply</button>
        </form>

        <div>
            <h2 class="mb-3 text-sm font-semibold text-gray-900">Points history</h2>
            <div class="space-y-2">
                @forelse($points['history'] as $row)
                    <div class="flex items-start justify-between rounded-2xl ly-bg-beige px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 rounded-full {{ $row['type'] === 'redeem' ? 'bg-red-500' : 'bg-emerald-500' }}"></span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $row['title'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['type_label'] }} · {{ $row['datetime'] }}</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold {{ str_starts_with($row['points_display'], '-') ? 'text-red-600' : 'ly-green' }}">{{ $row['points_display'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No history yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
