@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'mb-4 flex flex-col gap-4 sm:mb-6 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div>
        <h1 class="text-lg font-medium text-gray-900 sm:text-xl">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-xs text-gray-500 sm:text-sm">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions))
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
