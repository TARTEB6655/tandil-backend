<x-supervisor-layout>
    <div class="space-y-5 sm:space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight border-l-4 border-indigo-500 pl-3">
                    Notifications
                </h1>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('supervisor.notifications.mark-all-read') }}" class="inline">
                    @csrf
                    @foreach(request()->query() as $key => $value)
                        @if(is_string($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                        @endif
                    @endforeach
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 transition-colors shadow-sm">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/90 text-emerald-900 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50/90 text-red-900 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Total</p>
                        <p class="text-2xl font-bold text-indigo-700 tabular-nums">{{ $totalCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-indigo-50 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Unread</p>
                        <p class="text-2xl font-bold text-red-600 tabular-nums">{{ $unreadCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm ring-1 ring-slate-900/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Read</p>
                        <p class="text-2xl font-bold text-emerald-700 tabular-nums">{{ max(0, $totalCount - $unreadCount) }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <x-notification-inbox-toolbar route-name="supervisor.notifications.index" :show-audience-filter="false" />

        <x-notification-inbox-bulk-actions
            :destroy-all-route="route('supervisor.notifications.destroy-all')"
            destroy-all-confirm="Delete all notifications matching your current filters?"
        />

        <form method="POST" action="{{ route('supervisor.notifications.destroy-bulk') }}" id="form-notifications-bulk">
            @csrf
            <x-notification-inbox-rows :notifications="$notifications" route-name="supervisor.notifications" />
        </form>

        <x-notification-inbox-scripts />

        <x-notification-inbox-pagination :notifications="$notifications" />
    </div>
</x-supervisor-layout>

