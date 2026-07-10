@props(['title' => 'No results', 'description' => 'Try adjusting your filters or search query.'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-gray-50/50 px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900/30']) }}>
    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
        <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
    </div>
    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
    <p class="mt-1 max-w-sm text-sm text-gray-500">{{ $description }}</p>
    @if(isset($action))
        <div class="mt-4">{{ $action }}</div>
    @endif
</div>
