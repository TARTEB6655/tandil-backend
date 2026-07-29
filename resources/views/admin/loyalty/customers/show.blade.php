<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.loyalty.customers') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Customer points</h1>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Same payload as <code class="text-xs">GET /api/admin/loyalty/customers/{id}</code></p>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">{{ session('success') }}</div>
        @endif

        <div class="rounded-xl border-2 border-indigo-200 bg-gradient-to-br from-indigo-600 to-indigo-700 p-5 text-white shadow-md">
            <div class="flex items-center gap-4">
                @if(!empty($points['profile_picture_url']))
                    <img src="{{ $points['profile_picture_url'] }}" alt="" class="h-14 w-14 rounded-full object-cover ring-2 ring-white/40">
                @else
                    <div class="flex h-14 w-14 items-center justify-center rounded-full bg-white/20 text-lg font-semibold ring-2 ring-white/30">
                        {{ strtoupper(substr($points['name'] ?? '?', 0, 1)) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-lg font-semibold">{{ $points['name'] }}</p>
                    <p class="mt-1 text-sm text-indigo-100">{{ $points['email'] }}</p>
                    <p class="mt-1 text-sm text-indigo-200/80">{{ $points['city'] }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-center dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ number_format($points['balance']) }}</p>
                <p class="text-xs text-indigo-700 dark:text-indigo-300">Balance</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-center dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ number_format($points['earned']) }}</p>
                <p class="text-xs text-emerald-700 dark:text-emerald-300">Earned</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-center dark:border-rose-800 dark:bg-rose-950/30">
                <p class="text-2xl font-bold text-rose-900 dark:text-rose-100">{{ number_format($points['redeemed']) }}</p>
                <p class="text-xs text-rose-700 dark:text-rose-300">Redeemed</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.loyalty.customers.adjust', $customer->id) }}"
              class="space-y-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            @csrf
            <div>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Manual adjust</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Matches <code class="text-xs">POST /api/admin/loyalty/customers/{id}/adjust</code> — positive credits, negative deducts.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Amount (+/-)</label>
                <input type="number" name="amount" placeholder="50 or -20" required
                       class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                @error('amount')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reason</label>
                <input type="text" name="reason" placeholder="Optional note"
                       class="mt-1.5 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            </div>
            <button type="submit" class="ly-btn w-full rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm">Apply adjustment</button>
        </form>

        <div>
            <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Points history</h2>
            <div class="space-y-2">
                @forelse($points['history'] as $row)
                    <div class="flex items-start justify-between rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start gap-2">
                            <span class="mt-1.5 h-2 w-2 rounded-full {{ $row['type'] === 'redeem' ? 'bg-red-500' : 'bg-emerald-500' }}"></span>
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $row['title'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['type_label'] }} · {{ $row['datetime'] }}</p>
                            </div>
                        </div>
                        <p class="text-sm font-bold {{ str_starts_with($row['points_display'], '-') ? 'text-red-600' : 'text-emerald-600' }}">{{ $row['points_display'] }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No history yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-admin-layout>
