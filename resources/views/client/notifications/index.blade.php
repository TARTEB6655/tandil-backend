<x-client-layout>
    @php
        $activeFilter = request('filter', 'all');
    @endphp
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Notifications</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">All your updates in one place: tips, messages from admin, help & support, and more.</p>
            </div>
            @if($unreadCount > 0)
                <form action="{{ route('client.notifications.mark-all-read') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900 font-medium">Mark all as read</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1">
            <a href="{{ route('client.notifications.index', ['filter' => 'all', 'q' => request('q')]) }}" class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'all' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">All</a>
            <a href="{{ route('client.notifications.index', ['filter' => 'unread', 'q' => request('q')]) }}" class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'unread' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Unread</a>
            <a href="{{ route('client.notifications.index', ['filter' => 'read', 'q' => request('q')]) }}" class="px-3 py-1.5 text-xs sm:text-sm rounded-md {{ $activeFilter === 'read' ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100' }}">Read</a>
        </div>
        <form method="GET" action="{{ route('client.notifications.index') }}" class="flex items-center gap-2">
            <input type="hidden" name="filter" value="{{ $activeFilter }}" />
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search notifications" class="w-56 rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-gray-500 focus:outline-none" />
            <button type="submit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs text-white">Search</button>
        </form>
    </div>

    <!-- Bulk actions -->
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" id="select-all-notifications" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
            Select all on page
        </label>
        <button type="submit" form="form-notifications-bulk" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200" onclick="return document.querySelectorAll('input[name=\'ids[]\']:checked').length && confirm('Delete selected?');">Delete selected</button>
        <form method="POST" action="{{ route('client.notifications.destroy-all') }}" class="inline" onsubmit="return confirm('Delete ALL notifications?');">
            @csrf
            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-red-700 bg-red-50 rounded-lg hover:bg-red-100 border border-red-200">Delete all</button>
        </form>
    </div>

    <form method="POST" action="{{ route('client.notifications.destroy-bulk') }}" id="form-notifications-bulk">
        @csrf
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $title = $data['title'] ?? '';
                    $message = $data['message'] ?? class_basename($notification->type ?? '');
                    $isRead = !is_null($notification->read_at);
                @endphp
                <div class="notification-row group p-3 sm:p-4 hover:bg-gray-50 transition-colors cursor-pointer {{ !$isRead ? 'bg-blue-50/50' : '' }}"
                     data-open-url="{{ route('client.notifications.show', $notification->id) }}">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="flex-shrink-0 pt-0.5">
                            <input type="checkbox" name="ids[]" value="{{ $notification->id }}" class="notification-cb rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                        </div>
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <a href="{{ route('client.notifications.show', $notification->id) }}" class="flex-1 min-w-0 block">
                            @if($title)<p class="text-xs sm:text-sm mb-0.5 {{ !$isRead ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}">{{ $title }}</p>@endif
                            <p class="text-xs sm:text-sm {{ !$isRead ? 'font-semibold text-gray-900' : 'font-normal text-gray-700' }}">{{ \Illuminate\Support\Str::limit($message, 200) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </a>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <form action="{{ route('client.notifications.destroy', $notification->id) }}" method="POST" class="inline">@csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 transition-colors" title="Delete"><svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4h6v3M4 7h16" /></svg></button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 sm:p-12 text-center">
                    <p class="text-sm text-gray-500">No notifications yet. Tips and messages from admin will appear here.</p>
                </div>
            @endforelse
        </div>
        @if($notifications->hasPages())
            <div class="px-4 sm:px-6 py-3 border-t border-gray-200">{{ $notifications->links() }}</div>
        @endif
    </div>
    </form>
    <script>
        document.getElementById('select-all-notifications')?.addEventListener('change', function() {
            document.querySelectorAll('.notification-cb').forEach(function(cb) { cb.checked = this.checked; }, this);
        });
        document.querySelectorAll('.notification-row[data-open-url]').forEach(function(row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('input, button, form, label, a')) return;
                window.location.href = row.getAttribute('data-open-url');
            });
        });
    </script>
</x-client-layout>
