<x-admin-layout>
    <div class="space-y-4 sm:space-y-6">
        <!-- Page Header -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-medium text-gray-900">Notifications</h1>
                    <p class="mt-1 text-sm md:text-base text-gray-600">View and manage all your notifications</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.notifications.create') }}" 
                       class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors duration-200 shadow-sm hover:shadow-md">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Send Notification
                    </a>
                    @if($unreadCount > 0)
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" class="flex-shrink-0 inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors duration-200 shadow-sm hover:shadow-md">
                                Mark All as Read
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Bulk actions: Select all, Delete selected, Delete all -->
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" id="select-all-notifications" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                Select all on page
            </label>
            <button type="submit" form="form-notifications-bulk" id="btn-delete-selected" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200" onclick="return document.querySelectorAll('input[name=\'ids[]\']:checked').length && confirm('Delete selected notifications?');">
                Delete selected
            </button>
            <form method="POST" action="{{ route('admin.notifications.destroy-all') }}" class="inline" onsubmit="return confirm('Delete ALL your notifications?');">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200">
                    Delete all
                </button>
            </form>
        </div>

        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="bg-gray-50 border border-gray-200 text-gray-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-2 mb-4">
                <svg class="w-5 h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm">{{ session('error') }}</span>
            </div>
        @endif

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Notifications</p>
                        <p class="text-lg font-medium text-gray-900">{{ $notifications->total() }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-gray-100 flex items-center justify-center">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Unread</p>
                        <p class="text-lg font-medium text-red-600">{{ $unreadCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-red-50 flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Read</p>
                        <p class="text-lg font-medium text-gray-600">{{ $notifications->total() - $unreadCount }}</p>
                    </div>
                    <div class="h-12 w-12 rounded-xl bg-green-50 flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notifications List with checkboxes for bulk delete -->
        <form method="POST" action="{{ route('admin.notifications.destroy-bulk') }}" id="form-notifications-bulk">
            @csrf
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            @forelse($notifications as $notification)
                @php
                    $isUnread = is_null($notification->read_at);
                @endphp
                <div class="notification-row border-b border-gray-100 last:border-b-0 hover:bg-gray-50 transition-colors duration-150 cursor-pointer {{ $isUnread ? 'bg-blue-50/30' : '' }}"
                     data-open-url="{{ route('admin.notifications.show', $notification->id) }}">
                    <div class="px-5 py-4">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 pt-1">
                                <input type="checkbox" name="ids[]" value="{{ $notification->id }}" class="notification-cb rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                            </div>
                            <!-- Notification Icon -->
                            <div class="flex-shrink-0 mt-0.5">
                                @php
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
                                <div class="h-10 w-10 rounded-full {{ $iconBg }} {{ $iconBorder }} border flex items-center justify-center">
                                    @if(str_contains($type, 'Order'))
                                        <svg class="w-5 h-5 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                                        </svg>
                                    @elseif(str_contains($type, 'Visit'))
                                        <svg class="w-5 h-5 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @elseif(str_contains($type, 'Complaint'))
                                        <svg class="w-5 h-5 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 {{ $iconColorClass }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    @endif
                                </div>
                            </div>

                            <!-- Notification Content: click opens target and marks as read -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3">
                                    <a href="{{ route('admin.notifications.show', $notification->id) }}" class="flex-1 min-w-0 group block js-open-notification">
                                        <p class="text-sm mb-1 {{ $isUnread ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}">
                                            {{ $data['message'] ?? class_basename($type) }}
                                        </p>
                                        @if(isset($data['visit_id']))
                                            <p class="text-xs text-gray-600 mb-1">Visit ID: #{{ $data['visit_id'] }}</p>
                                        @endif
                                        @if(isset($data['subscription_id']))
                                            <p class="text-xs text-gray-600 mb-1">Subscription ID: #{{ $data['subscription_id'] }}</p>
                                        @endif
                                        @if(isset($data['order_id']))
                                            <p class="text-xs text-gray-600 mb-1">Order ID: #{{ $data['order_id'] }}</p>
                                        @endif
                                        <p class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                    </a>
                                    <div class="flex items-center gap-2 flex-shrink-0">
                                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification->id) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this notification?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="p-1.5 text-gray-400 hover:text-red-600 transition-colors"
                                                    title="Delete">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
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
            document.getElementById('select-all-notifications')?.addEventListener('change', function() {
                document.querySelectorAll('.notification-cb').forEach(function(cb) { cb.checked = this.checked; }, this);
            });
            document.querySelectorAll('.notification-row[data-open-url]').forEach(function(row) {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('input, button, form, label')) return;
                    const link = row.querySelector('.js-open-notification');
                    if (link) {
                        window.location.href = link.getAttribute('href');
                    }
                });
            });
        </script>

        <!-- Pagination -->
        @if($notifications->hasPages())
            <div class="flex justify-center">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
                    {{ $notifications->links() }}
                </div>
            </div>
        @endif
    </div>
</x-admin-layout>

