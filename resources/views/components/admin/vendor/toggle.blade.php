@props(['enabled' => false, 'action', 'label' => null])

<form method="POST" action="{{ $action }}" class="inline-flex items-center">
    @csrf
    <label class="relative inline-flex cursor-pointer items-center">
        <input type="checkbox" class="peer sr-only" @checked($enabled) onchange="this.form.submit()" />
        <div class="peer h-6 w-11 rounded-full bg-gray-200 transition-colors after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all peer-checked:bg-indigo-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:ring-2 peer-focus:ring-indigo-500/40 dark:bg-gray-700"></div>
        @if($label)
            <span class="ml-2 text-xs text-gray-600 dark:text-gray-400">{{ $label }}</span>
        @endif
    </label>
</form>
