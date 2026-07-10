@props([
    'label',
    'value',
    'subtitle' => null,
    'hint' => null,
    'accent' => 'text-gray-900 dark:text-gray-100',
])

<x-admin.vendor.stat-card :label="$label" :value="$value" :hint="$subtitle ?? $hint" :accent="$accent" {{ $attributes }} />
