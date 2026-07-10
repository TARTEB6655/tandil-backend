@props([
    'label',
    'value',
    'subtitle' => null,
    'accent' => 'text-indigo-600',
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200/80 bg-white/80 p-2.5 shadow-sm dark:border-gray-700 dark:bg-gray-900/70']) }}>
    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
    <p class="mt-0.5 text-lg font-bold tabular-nums leading-tight {{ $accent }}">{{ $value }}</p>
    @if($subtitle)
        <p class="mt-0.5 text-[10px] text-gray-500">{{ $subtitle }}</p>
    @endif
</div>
