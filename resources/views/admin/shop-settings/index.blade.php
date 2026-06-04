<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Shop delivery &amp; tax</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Set delivery by category: small items on <strong>bike</strong>, large items on <strong>car</strong>.
                    Fees apply at checkout and on Stripe payment.
                </p>
            </div>
            <a href="{{ route('admin.categories.index') }}"
               class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                Manage categories
            </a>
        </div>

        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="rounded-xl border border-blue-100 bg-blue-50/80 p-4 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
            <p class="font-medium">How checkout calculates delivery</p>
            <ul class="mt-2 list-disc pl-5 space-y-1 text-blue-800/90 dark:text-blue-200/90">
                <li>Each <strong>category</strong> in the cart adds its delivery fee once (not per product).</li>
                <li>Example: 2 small-item products in “Spices” = one bike fee; 1 large item in “Furniture” = one car fee.</li>
                <li>Categories without a fee use the global default below (once per order).</li>
            </ul>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Global checkout defaults</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Fallback delivery and VAT when a category has no custom fee.
            </p>

            <form method="POST" action="{{ route('admin.shop-settings.update-global') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Default shipping ({{ $currency }})</label>
                    <input type="number" name="shipping_amount" step="0.01" min="0"
                           value="{{ old('shipping_amount', $globalShipping) }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                    <p class="mt-1 text-xs text-gray-500">0 = free for fallback bucket</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tax %</label>
                    <input type="number" name="tax_percent" step="0.01" min="0" max="100"
                           value="{{ old('tax_percent', $taxPercent) }}"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                </div>
                <div>
                    <button type="submit" class="w-full md:w-auto px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Save global settings
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Per-category delivery fees</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Assign bike or car delivery and the fee for each category. Mobile admin API: <code class="text-xs bg-gray-100 dark:bg-gray-900 px-1 rounded">PUT /api/admin/settings/shop/category-shipping</code>
                </p>
            </div>

            <form method="POST" action="{{ route('admin.shop-settings.update-category-shipping') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Shipping type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-40">Shipping cost ({{ $currency }})</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-28">Tax %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                            @forelse($categoryRates as $i => $rate)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        {{ $rate['category_name'] }}
                                        <input type="hidden" name="rates[{{ $i }}][category_id]" value="{{ $rate['category_id'] }}">
                                    </td>
                                    <td class="px-4 py-3">
                                        <select name="rates[{{ $i }}][shipping_type]"
                                                class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                            @php $stype = $rate['shipping_type'] ?? $rate['delivery_type'] ?? ''; @endphp
                                            <option value="" {{ $stype === '' ? 'selected' : '' }}>—</option>
                                            <option value="bike" {{ $stype === 'bike' ? 'selected' : '' }}>Bike (small)</option>
                                            <option value="car" {{ $stype === 'car' ? 'selected' : '' }}>Car (large)</option>
                                        </select>
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="rates[{{ $i }}][shipping_cost]" step="0.01" min="0"
                                               value="{{ old('rates.'.$i.'.shipping_cost', $rate['shipping_cost'] ?? $rate['shipping_amount'] ?? '') }}"
                                               placeholder="Global ({{ number_format($globalShipping, 2) }})"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </td>
                                    <td class="px-4 py-3">
                                        <input type="number" name="rates[{{ $i }}][tax_percentage]" step="0.01" min="0" max="100"
                                               value="{{ old('rates.'.$i.'.tax_percentage', $rate['tax_percentage'] ?? '') }}"
                                               placeholder="Global"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">
                                        No categories yet.
                                        <a href="{{ route('admin.categories.create') }}" class="text-indigo-600 hover:underline">Create a category</a>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(count($categoryRates) > 0)
                    <div class="p-6 border-t border-gray-200 dark:border-gray-700">
                        <button type="submit" class="px-5 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-medium rounded-lg hover:opacity-90">
                            Save category delivery fees
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-admin-layout>
