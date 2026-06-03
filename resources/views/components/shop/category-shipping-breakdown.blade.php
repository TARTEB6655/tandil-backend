@if(!empty($breakdown))
    <div class="pl-3 space-y-1 border-l-2 border-gray-100 dark:border-gray-600">
        @foreach($breakdown as $shipLine)
            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>{{ $shipLine['category_name'] ?? 'Delivery' }}</span>
                <span>AED {{ number_format((float) ($shipLine['shipping_amount'] ?? 0), 2) }}</span>
            </div>
        @endforeach
    </div>
@endif
