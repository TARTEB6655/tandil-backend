<x-supervisor-layout>
    <div class="space-y-5 sm:space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 tracking-tight border-l-4 border-indigo-500 pl-3">
                    Notifications
                </h1>
                <p class="mt-2 text-sm sm:text-base text-slate-600 max-w-2xl">
                    Stay updated with visits, reports, and complaints in your areas.
                </p>
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

        <x-notification-inbox-toolbar route-name="supervisor.notifications.index" :show-audience-filter="false" />

        <div class="rounded-xl border border-slate-200 bg-slate-50/90 px-4 py-3 sm:px-5 flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-3 sm:gap-4">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 sm:mr-1">Bulk actions</p>
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 cursor-pointer select-none">
                <input type="checkbox" id="select-all-notifications" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0" />
                Select all on this page
            </label>
            <span id="selected-count" class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">0 selected</span>
            <span class="hidden sm:inline h-4 w-px bg-slate-200" aria-hidden="true"></span>
            <div class="flex flex-wrap items-center gap-2">
                <button type="submit" form="form-notifications-bulk" id="btn-delete-selected"
                        class="inline-flex items-center justify-center px-3 py-2 text-sm font-semibold rounded-lg border border-red-200 bg-white text-red-700 hover:bg-red-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled
                        onclick="return document.querySelectorAll('input[name=\'ids[]\']:checked').length && confirm('Delete selected notifications?');">
                    Delete selected
                </button>
                <form method="POST" action="{{ route('supervisor.notifications.destroy-all') }}" class="inline" onsubmit="return confirm('Delete all notifications?');">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center px-3 py-2 text-sm font-semibold rounded-lg border border-red-300 bg-red-600 text-white hover:bg-red-700 transition-colors shadow-sm">
                        Delete all
                    </button>
                </form>
            </div>
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

        <form method="POST" action="{{ route('supervisor.notifications.destroy-bulk') }}" id="form-notifications-bulk">
            @csrf
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden ring-1 ring-slate-900/5">
                @forelse($notifications as $notification)
                    @php
                        $isUnread = is_null($notification->read_at);
                        $type = $notification->type;
                        $data = $notification->data;
                        $iconColor = 'blue';
                        $iconBg = 'bg-blue-50';
                        $iconBorder = 'border-blue-100';
                        if (str_contains($type, 'Order') || str_contains($type, 'order')) {
                            $iconColor = 'blue';
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
                        $iconColorClass = match($iconColor) {
                            'blue' => 'text-blue-600',
                            'green' => 'text-green-600',
                            'amber' => 'text-amber-600',
                            'purple' => 'text-purple-600',
                            default => 'text-blue-600'
                        };
                    @endphp
                    <div class="notification-row border-b border-slate-100 last:border-b-0 hover:bg-slate-50/80 transition-colors duration-150 cursor-pointer {{ $isUnread ? 'bg-indigo-50/40' : '' }}"
                         data-open-url="{{ route('supervisor.notifications.show', $notification->id) }}">
                        <div class="px-4 py-2.5">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0 pt-1">
                                    <input type="checkbox" name="ids[]" value="{{ $notification->id }}" class="notification-cb rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-0" />
                                </div>
                                <div class="flex-shrink-0 mt-0.5">
                                    <div class="h-8 w-8 rounded-full {{ $iconBg }} {{ $iconBorder }} border flex items-center justify-center">
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
                                        <a href="{{ route('supervisor.notifications.show', $notification->id) }}" class="flex-1 min-w-0 group block js-open-notification">
                                            <p class="text-sm mb-1 {{ $isUnread ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}">
                                                {{ $data['message'] ?? class_basename($type) }}
                                            </p>
                                            @php
                                                $kindBadge = \App\Support\NotificationWebPresenter::kindBadge($type, is_array($data) ? $data : []);
                                                $audLabel = \App\Support\NotificationWebPresenter::audienceLabel(is_array($data) ? $data : []);
                                            @endphp
                                            <div class="flex flex-wrap items-center gap-1.5 mt-1 mb-1">
                                                <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-gray-100 text-gray-700">{{ $kindBadge }}</span>
                                            </div>
                                            @if(isset($data['visit_id']))
                                                <p class="text-xs text-gray-600 mb-1">Visit ID: #{{ $data['visit_id'] }}</p>
                                            @endif
                                            @if(isset($data['order_id']))
                                                <p class="text-xs text-gray-600 mb-1">Order ID: #{{ $data['order_id'] }}</p>
                                            @endif
                                        </a>
                                        <div class="flex items-center gap-3 flex-shrink-0">
                                            <div class="text-right">
                                                @if($audLabel)
                                                    <p class="mb-1">
                                                        <span class="inline-flex px-2 py-0.5 text-[10px] font-medium rounded-full bg-indigo-50 text-indigo-800">{{ $audLabel }}</span>
                                                    </p>
                                                @endif
                                                <p class="text-[10px] text-gray-500 leading-4">{{ $notification->created_at->diffForHumans() }}</p>
                                            </div>
                                            <button type="button"
                                                    class="p-1.5 text-red-500 hover:text-red-700 transition-colors js-delete-notification"
                                                    title="Delete"
                                                    data-delete-url="{{ route('supervisor.notifications.destroy', $notification->id) }}">
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
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <p class="text-sm font-semibold text-gray-900 mb-1">No notifications</p>
                        <p class="text-xs text-gray-500">You're all caught up!</p>
                    </div>
                @endforelse
            </div>
        </form>

        <script>
            const selectAll = document.getElementById('select-all-notifications');
            const selectedCount = document.getElementById('selected-count');
            const deleteSelected = document.getElementById('btn-delete-selected');
            const checkboxes = Array.from(document.querySelectorAll('.notification-cb'));
            function syncBulkUi() {
                const checked = checkboxes.filter(cb => cb.checked).length;
                if (selectedCount) selectedCount.textContent = `${checked} selected`;
                if (deleteSelected) deleteSelected.disabled = checked === 0;
                if (selectAll) selectAll.checked = checked > 0 && checked === checkboxes.length;
            }
            selectAll?.addEventListener('change', function() {
                checkboxes.forEach(cb => { cb.checked = this.checked; });
                syncBulkUi();
            });
            checkboxes.forEach(cb => cb.addEventListener('change', syncBulkUi));
            syncBulkUi();
            document.querySelectorAll('.notification-row[data-open-url]').forEach(function(row) {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('input, button, form, label')) return;
                    const link = row.querySelector('.js-open-notification');
                    if (link) window.location.href = link.getAttribute('href');
                });
            });
            document.querySelectorAll('.js-delete-notification').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const action = this.getAttribute('data-delete-url');
                    if (!action) return;
                    if (!confirm('Are you sure you want to delete this notification?')) return;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = action;
                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = @json(csrf_token());
                    form.appendChild(token);
                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        </script>

        @if($notifications->hasPages())
            <div class="flex justify-center">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3">
                    {{ $notifications->links() }}
                </div>
            </div>
        @endif
    </div>
</x-supervisor-layout>

