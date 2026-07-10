@props(['name' => 'V', 'src' => null, 'size' => 'md'])

@php
    $sizes = [
        'sm' => 'h-8 w-8 text-xs rounded-md',
        'md' => 'h-10 w-10 text-sm rounded-lg',
        'lg' => 'h-14 w-14 text-lg rounded-xl',
        'xl' => 'h-16 w-16 text-xl rounded-xl',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $initial = strtoupper(substr($name, 0, 1));
@endphp

@if($src)
    <img src="{{ $src }}" alt="" {{ $attributes->merge(['class' => "shrink-0 object-cover ring-1 ring-gray-200 dark:ring-gray-700 {$sizeClass}"]) }} />
@else
    <div {{ $attributes->merge(['class' => "flex shrink-0 items-center justify-center bg-gradient-to-br from-indigo-500 to-violet-600 font-semibold text-white {$sizeClass}"]) }}>
        {{ $initial }}
    </div>
@endif
