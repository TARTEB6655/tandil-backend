@if(!empty($breakdown))
    <div class="pl-3 space-y-1.5 border-l-2 border-gray-100 dark:border-gray-600 mt-1">
        @foreach($breakdown as $shipLine)
            <div class="flex justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="truncate min-w-0">{{ $shipLine['category_name'] ?? 'Delivery' }}</span>
                <span class="shrink-0 tabular-nums">AED {{ number_format((float) ($shipLine['shipping_cost'] ?? $shipLine['shipping_amount'] ?? 0), 2) }}</span>
            </div>
        @endforeach
    </div>
@endif
