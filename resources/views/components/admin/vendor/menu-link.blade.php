@props(['href' => '#', 'danger' => false])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors '.($danger ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-800')]) }}>
    {{ $slot }}
</a>
