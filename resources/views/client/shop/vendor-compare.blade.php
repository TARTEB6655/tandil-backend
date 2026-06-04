<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compare vendors</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50 min-h-screen p-4 sm:p-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="text-xl font-semibold text-gray-900">Compare vendors</h1>
        @if($comparison['reference_product'])
            <p class="text-sm text-gray-600 mt-1">For: <strong>{{ $comparison['reference_product']['name'] }}</strong></p>
        @endif
        <div class="mt-6 overflow-x-auto bg-white rounded-xl border border-gray-200 shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="px-4 py-3 text-left">Vendor</th>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-left">Price (AED)</th>
                    <th class="px-4 py-3 text-left">Stock</th>
                    <th class="px-4 py-3 text-left">Delivery</th>
                </tr></thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($comparison['vendors'] as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $row['vendor_name'] }}</td>
                            <td class="px-4 py-3">{{ $row['product_name'] }}</td>
                            <td class="px-4 py-3">{{ $row['price'] !== null ? number_format($row['price'], 2) : '—' }}</td>
                            <td class="px-4 py-3">{{ $row['in_stock'] ? $row['stock_quantity'].' in stock' : 'Out of stock' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $row['delivery_info'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No other vendors offer similar products.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
