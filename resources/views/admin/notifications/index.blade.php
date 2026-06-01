@php
    $isSystemWideInbox = $isSystemWideInbox ?? false;
    $inboxListRoute = $isSystemWideInbox ? 'admin.notifications.statistics' : 'admin.notifications.index';
@endphp
<x-admin-layout>
    <div class="space-y-5 sm:space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-gray-50 tracking-tight border-l-4 border-indigo-500 pl-3">
                    @if($isSystemWideInbox)
                        {{ __('admin.notification_statistics') }}
                    @else
                        {{ __('admin.notifications') }}
                    @endif
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0">
                @if($isSystemWideInbox)
                    <a href="{{ route('admin.notifications.index') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1" />
                        </svg>
                        {{ __('admin.my_notifications') }}
                    </a>
                @else
                    <a href="{{ route('admin.notifications.statistics') }}"
                       class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        {{ __('admin.notification_statistics') }}
                    </a>
                @endif
                <a href="{{ route('admin.notifications.delivery-stats') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Delivery analytics
                </a>
                <a href="{{ route('admin.notifications.broadcasts.index') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-indigo-200 dark:border-indigo-700 bg-indigo-50/80 dark:bg-indigo-950/40 text-indigo-800 dark:text-indigo-200 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    Broadcast log
                </a>
                <a href="{{ route('admin.notifications.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 transition-colors shadow-sm hover:shadow">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Send notification
                </a>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" class="inline">
                        @csrf
                        @foreach(request()->query() as $key => $value)
                            @if(is_string($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                            @endif
                        @endforeach
                        @if($isSystemWideInbox)
                            <input type="hidden" name="admin_notifications_index" value="1" />
                        @endif
                        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-gray-800 text-slate-800 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-gray-700 transition-colors shadow-sm">
                            Mark all as read
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/50 bg-emerald-50/90 dark:bg-emerald-950/30 text-emerald-900 dark:text-emerald-100 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/90 dark:bg-red-950/30 text-red-900 dark:text-red-100 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-red-200 dark:border-red-900/50 bg-red-50/90 dark:bg-red-950/30 text-red-900 dark:text-red-100 px-4 py-3 flex items-center gap-3">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">{{ $errors->first() }}</span>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm ring-1 ring-slate-900/5 dark:ring-white/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Total</p>
                        <p class="text-2xl font-bold text-indigo-700 dark:text-indigo-300 tabular-nums">{{ $totalCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm ring-1 ring-slate-900/5 dark:ring-white/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Unread</p>
                        <p class="text-2xl font-bold text-red-600 dark:text-red-400 tabular-nums">{{ $unreadCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-red-50 dark:bg-red-950/40 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-slate-700 p-5 shadow-sm ring-1 ring-slate-900/5 dark:ring-white/5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Read</p>
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400 tabular-nums">{{ max(0, $totalCount - $unreadCount) }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <x-notification-inbox-toolbar :route-name="$inboxListRoute" :show-audience-filter="$isSystemWideInbox" />

        <x-notification-inbox-bulk-actions
            :destroy-all-route="route('admin.notifications.destroy-all')"
            :destroy-all-confirm="$isSystemWideInbox ? __('admin.delete_all_notifications_confirm_global') : __('admin.delete_all_notifications_confirm_personal')"
            :system-wide-inbox="$isSystemWideInbox"
        />

        <!-- Notifications List with checkboxes for bulk delete -->
        <form method="POST" action="{{ route('admin.notifications.destroy-bulk') }}" id="form-notifications-bulk">
            @csrf
            @if($isSystemWideInbox)
                <input type="hidden" name="admin_notifications_index" value="1" />
            @endif
            <x-notification-inbox-rows
                :notifications="$notifications"
                route-name="admin.notifications"
                :show-query-suffix="$isSystemWideInbox ? '?from=stats' : ''"
            />
        </form>

        <x-notification-inbox-scripts />

        <x-notification-inbox-pagination :notifications="$notifications" />
    </div>
</x-admin-layout>

