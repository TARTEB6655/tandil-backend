@props([
    'variant' => 'secondary',
    'type' => 'button',
    'href' => null,
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500/40 focus:ring-offset-1 disabled:pointer-events-none disabled:opacity-50';
    $variants = [
        'primary' => 'bg-gray-900 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100',
        'brand' => 'bg-indigo-600 text-white hover:bg-indigo-500',
        'secondary' => 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800',
        'danger' => 'bg-rose-600 text-white hover:bg-rose-500',
        'ghost' => 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800',
    ];
    $class = $base.' '.($variants[$variant] ?? $variants['secondary']);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }}>{{ $slot }}</button>
@endif