@props([
    'label',
    'value',
    'subtitle' => null,
    'accent' => 'text-indigo-600',
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'group relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white/80 p-4 shadow-sm backdrop-blur transition duration-300 hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-900/70']) }}>
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/5 via-transparent to-purple-500/5 opacity-0 transition group-hover:opacity-100"></div>
    <div class="relative flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-bold tabular-nums {{ $accent }}">{{ $value }}</p>
            @if($subtitle)
                <p class="mt-1 text-xs text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
        @if($icon)
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300">
                {!! $icon !!}
            </div>
        @endif
    </div>
</div>
