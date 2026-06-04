@if(!empty($breakdown))
    <div class="pl-3 space-y-1.5 border-l-2 border-gray-100 dark:border-gray-600 mt-1">
        @foreach($breakdown as $shipLine)
            @php
                $type = $shipLine['shipping_type'] ?? $shipLine['delivery_type'] ?? null;
                $typeLabel = $shipLine['shipping_type_label'] ?? $shipLine['delivery_type_label'] ?? null;
            @endphp
            <div class="flex justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5 min-w-0">
                    @if($type === 'bike')
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-sky-100 text-sky-700" title="Bike delivery">🛵</span>
                    @elseif($type === 'car')
                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded bg-amber-100 text-amber-800" title="Car delivery">🚗</span>
                    @endif
                    <span class="truncate">{{ $shipLine['category_name'] ?? 'Delivery' }}@if($typeLabel && $type) <span class="text-gray-400">({{ \App\Models\Category::deliveryTypeShortLabel($type) }})</span>@endif</span>
                </span>
                <span class="shrink-0 tabular-nums">AED {{ number_format((float) ($shipLine['shipping_amount'] ?? 0), 2) }}</span>
            </div>
        @endforeach
    </div>
@endif
