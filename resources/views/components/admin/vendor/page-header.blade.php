@props(['title', 'description' => null])

<div class="flex flex-col gap-4 border-b border-gray-200/80 pb-6 dark:border-gray-800 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-50">{{ $title }}</h1>
        @if($description)
            <p class="mt-1 max-w-2xl text-sm leading-relaxed text-gray-500 dark:text-gray-400">{{ $description }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
