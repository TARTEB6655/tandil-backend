@props([
    'lines' => [],
    'basePrice' => null,
    'unitPrice' => null,
])

@if(count($lines) > 0)
    <div {{ $attributes->merge(['class' => 'mt-3 space-y-2']) }}>
        @foreach($lines as $line)
            <div class="flex items-center gap-2.5 rounded-lg border border-gray-100 bg-gray-50/80 px-2.5 py-2">
                @if(!empty($line['image_url']))
                    <img src="{{ $line['image_url'] }}{{ str_contains($line['image_url'], '?') ? '&' : '?' }}w=80"
                         alt="{{ $line['label'] }}"
                         width="40"
                         height="40"
                         loading="lazy"
                         decoding="async"
                         class="h-10 w-10 shrink-0 rounded-md object-cover ring-1 ring-gray-200">
                @else
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-white ring-1 ring-gray-200 text-gray-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $line['group_name'] }}</p>
                    <p class="text-sm font-medium leading-snug text-gray-900">{{ $line['label'] }}</p>
                    @if(!empty($line['subtitle']))
                        <p class="text-xs text-gray-500 mt-0.5">{{ $line['subtitle'] }}</p>
                    @endif
                </div>
                @if(($line['price_modifier'] ?? 0) > 0)
                    <span class="shrink-0 text-xs font-semibold tabular-nums text-gray-800">
                        + AED {{ number_format((float) $line['price_modifier'], 2) }}
                    </span>
                @endif
            </div>
        @endforeach

        @if($basePrice !== null && $unitPrice !== null && round((float) $unitPrice - (float) $basePrice, 2) > 0)
            <p class="text-xs text-gray-500 pt-0.5">
                Unit price:
                <span class="tabular-nums">AED {{ number_format((float) $basePrice, 2) }}</span>
                <span class="text-gray-400">+</span>
                <span class="tabular-nums">AED {{ number_format((float) $unitPrice - (float) $basePrice, 2) }}</span>
                <span class="text-gray-400">options</span>
                <span class="text-gray-400">=</span>
                <span class="font-medium text-gray-800 tabular-nums">AED {{ number_format((float) $unitPrice, 2) }}</span>
            </p>
        @endif
    </div>
@endif
