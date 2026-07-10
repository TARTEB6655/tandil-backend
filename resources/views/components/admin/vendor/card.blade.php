@props(['title' => null, 'padding' => true])

<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    @if($title)
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
        </div>
    @endif
    <div @class(['px-5 py-4' => $padding])>
        {{ $slot }}
    </div>
</div>
