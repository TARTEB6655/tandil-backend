<x-admin-layout>
    <div class="space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-medium text-gray-900 dark:text-gray-100">Shop delivery &amp; tax</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Global default shipping, VAT, and per-category delivery (bike vs car pricing).
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

        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Global checkout defaults</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                Used when a category has no custom delivery fee, or for products without a category (once per order).
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
                    Example: small items (bike) = lower fee, large items (car) = higher fee. Checkout adds one fee per category in the cart.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.shop-settings.update-category-shipping') }}">
                @csrf
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-48">Delivery fee ({{ $currency }})</th>
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
                                        <input type="number" name="rates[{{ $i }}][shipping_amount]" step="0.01" min="0"
                                               value="{{ old('rates.'.$i.'.shipping_amount', $rate['shipping_amount']) }}"
                                               placeholder="Use global ({{ number_format($globalShipping, 2) }})"
                                               class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500">
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
