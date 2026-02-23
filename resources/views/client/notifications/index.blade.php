<x-client-layout>
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
            <div>
                <h1 class="text-lg sm:text-xl font-medium text-gray-900">Notifications</h1>
                <p class="mt-1 text-xs sm:text-sm text-gray-500">Tips and updates from the admin team.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-3 sm:p-4 rounded-md">
            <p class="text-xs sm:text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    <!-- Tips (same as API: notifications = published tips) -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($tips as $tip)
                <div class="p-3 sm:p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-900 mb-1">{{ $tip->title }}</p>
                            <p class="text-xs sm:text-sm text-gray-600">{{ $tip->content }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $tip->created_at->diffForHumans() }}</p>
                        </div>
                        <form action="{{ route('client.notifications.mark-read', $tip->id) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-900 whitespace-nowrap">Mark as read</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="p-8 sm:p-12 text-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1" />
                    </svg>
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No notifications</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Tips from the admin will appear here.</p>
                </div>
            @endforelse
        </div>
        @if($tips->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200">
                {{ $tips->links() }}
            </div>
        @endif
    </div>
</x-client-layout>

