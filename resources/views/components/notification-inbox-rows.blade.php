@props([
    'notifications',
    /** e.g. client.notifications */
    'routeName',
    'showQuerySuffix' => '',
    'showSelectAllHeader' => true,
])

<div class="bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden ring-1 ring-slate-900/5 dark:ring-white/5">
    @if($showSelectAllHeader && $notifications->count() > 0)
        <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/50 px-4 py-2.5">
            <div class="flex h-8 w-8 shrink-0 items-center justify-center">
                <input type="checkbox"
                       id="select-all-notifications"
                       class="notification-inbox-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 dark:border-slate-600 dark:bg-gray-800"
                       aria-label="Select all on this page" />
            </div>
            <label for="select-all-notifications" class="text-sm font-semibold text-slate-700 dark:text-slate-200 cursor-pointer select-none">
                Select all
            </label>
        </div>
    @endif
    @forelse($notifications as $notification)
        @php
            $isUnread = is_null($notification->read_at);
            $type = $notification->type ?? '';
            $data = $notification->data ?? [];
            $iconColor = 'blue';
            $iconBg = 'bg-blue-50';
            $iconBorder = 'border-blue-100';
            if (str_contains($type, 'Order') || str_contains($type, 'order')) {
                $iconBg = 'bg-blue-50';
                $iconBorder = 'border-blue-100';
            } elseif (str_contains($type, 'Visit') || str_contains($type, 'visit')) {
                $iconColor = 'green';
                $iconBg = 'bg-green-50';
                $iconBorder = 'border-green-100';
            } elseif (str_contains($type, 'Complaint') || str_contains($type, 'complaint')) {
                $iconColor = 'amber';
                $iconBg = 'bg-amber-50';
                $iconBorder = 'border-amber-100';
            } elseif (str_contains($type, 'Report') || str_contains($type, 'report')) {
                $iconColor = 'purple';
                $iconBg = 'bg-purple-50';
                $iconBorder = 'border-purple-100';
            }
            $iconColorClass = match ($iconColor) {
                'blue' => 'text-blue-600 dark:text-blue-400',
                'green' => 'text-green-600 dark:text-green-400',
                'amber' => 'text-amber-600 dark:text-amber-400',
                'purple' => 'text-purple-600 dark:text-purple-400',
                default => 'text-blue-600 dark:text-blue-400',
            };
            $meta = \App\Support\AdminNotificationTargetUrl::meta(is_array($data) ? $data : []);
            $notificationOpenUrl = str_starts_with($routeName, 'admin.')
                ? route('admin.notifications.read-and-redirect', $notification->id).$showQuerySuffix
                : route($routeName . '.show', $notification->id).$showQuerySuffix;
        @endphp
        <div class="notification-row border-b border-slate-100 dark:border-slate-700/80 last:border-b-0 hover:bg-slate-50/80 dark:hover:bg-slate-900/50 transition-colors duration-150 cursor-pointer {{ $isUnread ? 'bg-indigo-50/40 dark:bg-indigo-950/25' : '' }}"
             data-open-url="{{ $notificationOpenUrl }}">
            <div class="px-4 py-2.5">
                <div class="flex items-start gap-4">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center">
                        <input type="checkbox" name="ids[]" value="{{ $notification->id }}" class="notification-cb notification-inbox-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0 dark:border-slate-600 dark:bg-gray-800" />
                    </div>
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="h-8 w-8 rounded-full {{ $iconBg }} dark:opacity-90 {{ $iconBorder }} border flex items-center justify-center">
                            @if(str_contains($type, 'Order'))
                                <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                </svg>
                            @elseif(str_contains($type, 'Visit'))
                                <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @elseif(str_contains($type, 'Complaint'))
                                <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            @else
                                <svg class="w-4 h-4 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            @endif
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start justify-between gap-3">
                            <a href="{{ $notificationOpenUrl }}" class="flex-1 min-w-0 group block js-open-notification">
                                <p class="text-sm mb-1 {{ $isUnread ? 'font-semibold text-gray-900 dark:text-gray-100' : 'font-normal text-gray-700 dark:text-gray-300' }}">
                                    {{ is_array($data) ? ($data['message'] ?? class_basename($type)) : class_basename($type) }}
                                </p>
                                @php
                                    $kindBadge = \App\Support\NotificationWebPresenter::kindBadge($type, is_array($data) ? $data : []);
                                    $audLabel = \App\Support\NotificationWebPresenter::audienceLabel(is_array($data) ? $data : []);
                                @endphp
                                <div class="flex flex-wrap items-center gap-1.5 mt-1 mb-1">
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200">{{ $kindBadge }}</span>
                                </div>
                                @if(is_array($data) && isset($data['visit_id']))
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Visit ID: #{{ $data['visit_id'] }}</p>
                                @endif
                                @if(is_array($data) && isset($data['subscription_id']))
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Subscription ID: #{{ $data['subscription_id'] }}</p>
                                @endif
                                @if(is_array($data) && isset($data['order_id']))
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Order ID: #{{ $data['order_id'] }}</p>
                                @endif
                                @if(is_array($data) && isset($data['employee_id']))
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Employee ID: #{{ $data['employee_id'] }}</p>
                                @endif
                                @if(in_array($meta['entity'] ?? null, ['vendor', 'vendor_application'], true) && ! empty($meta['vendor_id']))
                                    <p class="text-xs text-indigo-600 dark:text-indigo-400 mb-1 font-medium">Vendor application — open to review profile, documents, approve or reject</p>
                                @endif
                            </a>
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <div class="text-right">
                                    @if($audLabel)
                                        <p class="mb-1">
                                            <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-indigo-50 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200">{{ $audLabel }}</span>
                                        </p>
                                    @endif
                                    <p class="text-[10px] text-gray-500 dark:text-gray-400 leading-4">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                                <button type="button"
                                        class="p-1.5 text-red-500 hover:text-red-700 transition-colors js-delete-notification"
                                        title="Delete"
                                        data-delete-url="{{ route($routeName . '.destroy', $notification->id) }}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="px-5 py-12 text-center">
            <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
            </svg>
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">No notifications</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">You're all caught up!</p>
        </div>
    @endforelse
</div>
