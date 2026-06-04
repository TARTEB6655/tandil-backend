@php
    $categoryModel = $category ?? null;
    $shippingType = old('shipping_type', $categoryModel->shipping_type ?? $categoryModel->delivery_type ?? '');
    $shippingCost = old('shipping_cost', $categoryModel->shipping_cost ?? $categoryModel->shipping_amount ?? '');
    $taxPercentage = old('tax_percentage', $categoryModel->tax_percentage ?? '');
@endphp

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/40 p-5 space-y-5">
    <div>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Shipping &amp; Tax Settings</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Shipping cost applied to all products in this category. Bike for small items, car for large items.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="shipping_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shipping Type *</label>
            <select id="shipping_type" name="shipping_type" required
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                <option value="" disabled {{ $shippingType === '' ? 'selected' : '' }}>Select delivery type</option>
                <option value="bike" {{ $shippingType === 'bike' ? 'selected' : '' }}>Bike (Small Products)</option>
                <option value="car" {{ $shippingType === 'car' ? 'selected' : '' }}>Car (Large Products)</option>
            </select>
            @error('shipping_type')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('delivery_type')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="shipping_cost" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Shipping Cost (AED) *</label>
            <input type="number" id="shipping_cost" name="shipping_cost" step="0.01" min="0" required
                   value="{{ $shippingCost }}"
                   placeholder="e.g. 50, 100, 150"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">0 = free shipping for this category.</p>
            @error('shipping_cost')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
            @error('shipping_amount')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="tax_percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tax Percentage (%) *</label>
            <input type="number" id="tax_percentage" name="tax_percentage" step="0.01" min="0" max="100" required
                   value="{{ $taxPercentage }}"
                   placeholder="e.g. 5, 10, 15"
                   class="w-full max-w-xs px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Applied to product subtotal for items in this category at checkout.</p>
            @error('tax_percentage')
                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
