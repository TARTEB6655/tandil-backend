@props(['title', 'canvasId'])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:p-6']) }}>
    <h3 class="mb-3 text-base font-medium text-gray-900 sm:mb-4 sm:text-lg">{{ $title }}</h3>
    <div class="h-48 sm:h-56 md:h-64">
        <canvas id="{{ $canvasId }}"></canvas>
    </div>
</div>
