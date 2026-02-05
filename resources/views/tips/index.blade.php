<x-dynamic-component :component="$layoutComponent">
    <!-- Page Header -->
    <div class="mb-4 sm:mb-6">
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">Tips</h1>
        <p class="mt-1 text-xs sm:text-sm text-gray-500">Helpful tips and updates from the team. Check here for seasonal advice and best practices.</p>
    </div>

    <!-- Tips List -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="divide-y divide-gray-200">
            @forelse($tips as $tip)
                <a href="{{ route($routeBase . '.show', $tip->id) }}" class="block p-3 sm:p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start gap-3 sm:gap-4">
                        <div class="flex-shrink-0">
                            <div class="h-8 w-8 sm:h-10 sm:w-10 rounded-full bg-amber-100 flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs sm:text-sm font-medium text-gray-900 mb-1">{{ $tip->title }}</p>
                            <p class="text-xs text-gray-500 line-clamp-2">{{ Str::limit(strip_tags($tip->content), 120) }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ $tip->created_at->format('M d, Y') }} · {{ ucfirst($tip->type ?? 'general') }}</p>
                        </div>
                        <div class="flex-shrink-0">
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </a>
            @empty
                <div class="p-8 sm:p-12 text-center">
                    <svg class="w-12 h-12 sm:w-16 sm:h-16 text-gray-300 mx-auto mb-3 sm:mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                    <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">No tips yet</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Tips from the team will appear here when they are published.</p>
                </div>
            @endforelse
        </div>

        @if($tips->hasPages())
            <div class="px-4 sm:px-6 py-3 sm:py-4 border-t border-gray-200 bg-gray-50/50">
                {{ $tips->withQueryString()->links('pagination::tailwind') }}
            </div>
        @endif
    </div>
</x-dynamic-component>
