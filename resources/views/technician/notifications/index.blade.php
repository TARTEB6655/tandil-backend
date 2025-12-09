<x-technician-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Notifications</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Stay updated with your visit assignments and updates.</p>
            </div>
            @if($unreadCount > 0)
                <form action="{{ route('technician.notifications.mark-all-read') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="text-xs sm:text-sm text-indigo-600 hover:text-indigo-900 font-medium">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Notifications List -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($notifications as $notification)
                @php
                    $data = $notification->data;
                    $type = $notification->type;
                    $isRead = !is_null($notification->read_at);
                @endphp
                <div class="p-3 sm:p-4 hover:bg-gray-50 transition-colors {{ !$isRead ? 'bg-blue-50' : '' }}">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 sm:gap-0">
                                <div class="flex-1">
                                    <p class="text-xs sm:text-sm font-medium text-gray-900 mb-1">
                                        {{ $data['message'] ?? class_basename($type) }}
                                    </p>
                                    @if(isset($data['visit_id']))
                                        <p class="text-xs text-gray-500">Visit ID: #{{ $data['visit_id'] }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                                </div>
                                @if(!$isRead)
                                    <form action="{{ route('technician.notifications.mark-read', $notification->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-900 whitespace-nowrap">
                                            Mark as read
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if(!$isRead)
                            <div class="flex-shrink-0">
                                <span class="h-2 w-2 bg-red-500 rounded-full"></span>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 sm:p-12 text-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1" />
                    </svg>
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No notifications</h3>
                    <p class="text-xs sm:text-sm text-gray-500">You're all caught up!</p>
                </div>
            @endforelse
        </div>
        
        @if($notifications->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-technician-layout>
