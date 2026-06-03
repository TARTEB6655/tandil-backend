<div>
    <label for="shipping_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Delivery fee (AED)</label>
    <input type="number" id="shipping_amount" name="shipping_amount" step="0.01" min="0"
           value="{{ old('shipping_amount', isset($category) && $category->shipping_amount !== null ? $category->shipping_amount : '') }}"
           placeholder="Leave empty to use shop default"
           class="w-full max-w-xs px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Per-category delivery (e.g. bike for small, car for large). Used at checkout for products in this category.</p>
    @error('shipping_amount')
        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
