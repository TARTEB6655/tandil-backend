@if(!empty($breakdown))
    <div class="mt-2 space-y-1 border-t border-gray-100 pt-2">
        @foreach($breakdown as $taxLine)
            <div class="flex justify-between text-xs text-gray-500">
                <span>
                    {{ $taxLine['category_name'] ?? 'Category' }}
                    ({{ number_format((float) ($taxLine['tax_percentage'] ?? 0), 1) }}%)
                </span>
                <span>AED {{ number_format((float) ($taxLine['tax_amount'] ?? 0), 2) }}</span>
            </div>
        @endforeach
    </div>
@endif
