<x-admin-layout>
    @include('admin.loyalty._theme')

    <div class="mx-auto max-w-3xl space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.loyalty.index') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">Rewards</h1>
            </div>
            <a href="{{ route('admin.loyalty.rewards.create') }}" class="ly-btn inline-flex items-center rounded-lg px-3 py-2 text-sm font-semibold">+ Add reward</a>
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl ly-bg-beige p-5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-base font-semibold text-gray-900">Rewards that feel worth earning</p>
                    <p class="mt-1 text-sm text-gray-600">Create offers, control availability, and keep redemption options fresh for every customer segment.</p>
                </div>
                <a href="{{ route('admin.loyalty.rewards.create') }}" class="ly-btn shrink-0 rounded-lg px-3 py-2 text-sm font-semibold">+ Add reward</a>
            </div>
            <div class="mt-4 grid grid-cols-3 gap-2 rounded-xl bg-white px-3 py-3 text-center text-sm">
                <div><p class="font-bold ly-green">{{ $summary['total'] }}</p><p class="text-xs text-gray-500">Total</p></div>
                <div><p class="font-bold ly-green">{{ $summary['active'] }}</p><p class="text-xs text-gray-500">Active</p></div>
                <div><p class="font-bold ly-green">{{ $summary['starts_at'] }}</p><p class="text-xs text-gray-500">Starts at</p></div>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($rewards as $reward)
                <div class="rounded-2xl ly-bg-beige p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ $reward['title'] }}</p>
                            <p class="mt-1 text-sm font-semibold ly-green">{{ $reward['points_label'] }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ $reward['cities'] }} · {{ ($reward['customer_targeting'] ?? '') === 'specific' ? 'Specific customer' : 'All customers' }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $reward['is_active'] ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600' }}">{{ $reward['status'] }}</span>
                    </div>
                    <div class="mt-4 flex items-center justify-between border-t border-black/5 pt-3">
                        <form method="POST" action="{{ route('admin.loyalty.rewards.toggle', $reward['id']) }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="is_active" value="{{ $reward['is_active'] ? '0' : '1' }}">
                            <span class="text-sm text-gray-600">Enabled</span>
                            <button type="submit" class="relative inline-flex h-7 w-12 items-center rounded-full {{ $reward['is_active'] ? 'ly-toggle-on' : 'bg-gray-300' }}">
                                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow {{ $reward['is_active'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                            </button>
                        </form>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.loyalty.rewards.edit', $reward['id']) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#1B4332]/30 text-[#1B4332] hover:bg-white">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.loyalty.rewards.destroy', $reward['id']) }}" onsubmit="return confirm('Delete this reward?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 text-red-600 hover:bg-white">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">No rewards yet. Create your first reward.</div>
            @endforelse
        </div>
    </div>
</x-admin-layout>
