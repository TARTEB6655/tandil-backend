@props([
    'label',
    'value',
    'subtitle' => null,
    'color' => 'indigo',
    'href' => null,
])

@php
    $colors = [
        'blue' => ['text' => 'text-blue-600', 'bg' => 'bg-blue-50'],
        'green' => ['text' => 'text-green-600', 'bg' => 'bg-green-50'],
        'purple' => ['text' => 'text-purple-600', 'bg' => 'bg-purple-50'],
        'amber' => ['text' => 'text-amber-600', 'bg' => 'bg-amber-50'],
        'indigo' => ['text' => 'text-indigo-600', 'bg' => 'bg-indigo-50'],
        'red' => ['text' => 'text-red-600', 'bg' => 'bg-red-50'],
        'teal' => ['text' => 'text-teal-600', 'bg' => 'bg-teal-50'],
    ];
    $c = $colors[$color] ?? $colors['indigo'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 p-4 sm:p-5 md:p-6 shadow-sm hover:shadow-md transition-shadow duration-200'.($href ? ' block hover:border-indigo-200' : '')]) }}
>
    <div class="flex items-center justify-between">
        <div class="min-w-0 flex-1">
            <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="text-base font-medium sm:text-lg {{ $c['text'] }}">{{ $value }}</p>
            @if($subtitle)
                <p class="mt-1 text-xs text-gray-500 sm:mt-2">{{ $subtitle }}</p>
            @endif
        </div>
        @if(isset($icon))
            <div class="ml-3 flex-shrink-0 sm:ml-4">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl sm:h-10 sm:w-10 md:h-12 md:w-12 {{ $c['bg'] }}">
                    {{ $icon }}
                </div>
            </div>
        @endif
    </div>
</{{ $tag }}>
