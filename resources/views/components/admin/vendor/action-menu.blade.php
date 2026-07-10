@props(['align' => 'right'])

<div x-data="{ open: false }" class="relative inline-block text-left" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:hover:bg-gray-800 dark:hover:text-gray-200"
            aria-label="Actions">
        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/></svg>
    </button>
    <div x-show="open" x-cloak @click.away="open = false"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="transform opacity-0 scale-95"
         x-transition:enter-end="transform opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="transform opacity-100 scale-100"
         x-transition:leave-end="transform opacity-0 scale-95"
         class="absolute z-30 mt-1 w-48 origin-top-right rounded-lg border border-gray-200 bg-white py-1 shadow-lg ring-1 ring-black/5 dark:border-gray-700 dark:bg-gray-900 {{ $align === 'right' ? 'right-0' : 'left-0' }}">
        {{ $slot }}
    </div>
</div>
