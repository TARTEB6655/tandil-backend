<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Campaigns</h1>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Same fields as <code class="text-xs">GET /api/admin/loyalty/campaigns</code></p>
                </div>
            </div>
            <a href="{{ route('admin.loyalty.campaigns.create') }}" class="ly-btn inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium shadow-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New campaign
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">{{ session('success') }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Total</p>
                <p class="mt-1 text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ $summary['total'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Live</p>
                <p class="mt-1 text-2xl font-bold text-emerald-900 dark:text-emerald-100">{{ $summary['live'] }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-950/30">
                <p class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">Top boost</p>
                <p class="mt-1 text-2xl font-bold text-violet-900 dark:text-violet-100">{{ $summary['top_boost'] }}</p>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($campaigns as $campaign)
                <div class="rounded-xl border-2 border-gray-200 bg-white p-5 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $campaign['title'] }}</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">{{ $campaign['boost_label'] }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $campaign['date_range'] }}</span>
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {{ $campaign['cities'] }} · {{ $campaign['customer_targeting_label'] ?? 'All customers' }}
                            </p>
                            @if(($campaign['customer_targeting'] ?? '') === 'specific' && !empty($campaign['specific_customers']))
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach($campaign['specific_customers'] as $name)
                                        <span class="rounded-full border border-indigo-200 bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:border-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">{{ $name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $campaign['status'] === 'Active' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">{{ $campaign['status'] }}</span>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700">
                        <form method="POST" action="{{ route('admin.loyalty.campaigns.toggle', $campaign['id']) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="is_enabled" value="{{ $campaign['is_enabled'] ? '0' : '1' }}">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Enabled</span>
                            <button type="submit" class="relative inline-flex h-7 w-12 items-center rounded-full {{ $campaign['is_enabled'] ? 'ly-toggle-on' : 'bg-gray-300' }}">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow {{ $campaign['is_enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </form>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.loyalty.campaigns.edit', $campaign['id']) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-700 dark:text-indigo-300">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.loyalty.campaigns.destroy', $campaign['id']) }}" onsubmit="return confirm('Delete this campaign?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center dark:border-gray-600 dark:bg-gray-800">
                    <p class="text-sm text-gray-500">No campaigns yet.</p>
                    <a href="{{ route('admin.loyalty.campaigns.create') }}" class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:underline">Create your first campaign</a>
                </div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
