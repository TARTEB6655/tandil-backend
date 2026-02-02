<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Recent Activities</h1>
                <p class="mt-1 text-sm text-gray-500">All recent activity across subscriptions, orders, visits, and more.</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="w-4 h-4 mr-2 -ml-1 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </a>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-900">Activity Feed</h2>
                @if($activities->total() > 0)
                    @php
                        $from = ($activities->currentPage() - 1) * $activities->perPage() + 1;
                        $to = min($activities->currentPage() * $activities->perPage(), $activities->total());
                    @endphp
                    <span class="text-sm text-gray-500">Showing {{ $from }}–{{ $to }} of {{ $activities->total() }}</span>
                @endif
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($activities->items() as $activity)
                    <div class="px-5 py-4 flex items-start gap-4 hover:bg-gray-50 transition-colors">
                        @if(($activity['icon_type'] ?? '') === 'success' || ($activity['icon_type'] ?? '') === 'check')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'user_add')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-700/10">
                                <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'warning')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100">
                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'error')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        @elseif(($activity['icon_type'] ?? '') === 'order')
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                            </div>
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100">
                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $activity['description'] ?? '' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $activity['timestamp'] ?? '' }}</p>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center text-sm text-gray-500">No recent activities yet.</div>
                @endforelse
            </div>
            @if($activities->hasPages())
                <div class="px-5 py-4 border-t border-gray-200 bg-gray-50">
                    {{ $activities->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
