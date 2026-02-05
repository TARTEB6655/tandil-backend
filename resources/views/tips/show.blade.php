<x-dynamic-component :component="$layoutComponent">
    <!-- Back link -->
    <div class="mb-4">
        <a href="{{ route($routeBase . '.index') }}" class="inline-flex items-center text-xs sm:text-sm text-gray-500 hover:text-gray-700">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Tips
        </a>
    </div>

    <!-- Tip content -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-6 md:p-8">
            <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                <span>{{ $tip->created_at->format('M d, Y') }}</span>
                <span>·</span>
                <span>{{ ucfirst($tip->type ?? 'general') }}</span>
            </div>
            <h1 class="text-lg sm:text-xl font-semibold text-gray-900 mb-4">{{ $tip->title }}</h1>
            <div class="prose prose-sm sm:prose max-w-none text-gray-700 whitespace-pre-wrap">{{ $tip->content }}</div>
        </div>
    </div>
</x-dynamic-component>
