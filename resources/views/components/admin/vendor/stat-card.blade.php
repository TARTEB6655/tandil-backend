@props([
    'label',
    'value',
    'hint' => null,
    'trend' => null,
    'accent' => 'text-gray-900 dark:text-gray-100',
])

<div {{ $attributes->merge(['class' => 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md dark:border-gray-800 dark:bg-gray-900']) }}>
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
    <p class="mt-2 text-2xl font-semibold tabular-nums tracking-tight {{ $accent }}">{{ $value }}</p>
    @if($hint)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif
    @if($trend)
        <p class="mt-1 text-xs font-medium text-emerald-600">{{ $trend }}</p>
    @endif
</div>
