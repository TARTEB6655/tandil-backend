<x-vendor-layout>
    <div class="space-y-5 sm:space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="border-l-4 border-indigo-500 pl-3 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                    Notifications
                </h1>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('vendor.notifications.mark-all-read') }}" class="inline shrink-0">
                    @csrf
                    @foreach(request()->query() as $key => $value)
                        @if(is_string($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                        @endif
                    @endforeach
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition-colors hover:bg-slate-50">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if(session('success'))
            <div class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-3 text-emerald-900">
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="flex items-center gap-3 rounded-xl border border-red-200 bg-red-50/90 px-4 py-3 text-red-900">
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total</p>
                <p class="text-2xl font-bold tabular-nums text-indigo-700">{{ $totalCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Unread</p>
                <p class="text-2xl font-bold tabular-nums text-red-600">{{ $unreadCount }}</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <p class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-slate-500">Read</p>
                <p class="text-2xl font-bold tabular-nums text-emerald-700">{{ max(0, $totalCount - $unreadCount) }}</p>
            </div>
        </div>

        <x-notification-inbox-toolbar route-name="vendor.notifications.index" :show-audience-filter="false" />

        <x-notification-inbox-bulk-actions
            :destroy-all-route="route('vendor.notifications.destroy-all')"
            destroy-all-confirm="Delete all notifications matching your current filters?"
        />

        <form method="POST" action="{{ route('vendor.notifications.destroy-bulk') }}" id="form-notifications-bulk">
            @csrf
            <x-notification-inbox-rows :notifications="$notifications" route-name="vendor.notifications" />
        </form>

        <x-notification-inbox-scripts />
        <x-notification-inbox-pagination :notifications="$notifications" />
    </div>
</x-vendor-layout>
