@php
    $coupon = $coupon ?? null;
    $selectedCategoryIds = $selectedCategoryIds ?? [];
    $selectedServiceIds = $selectedServiceIds ?? [];
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-6" x-data="{ appliesTo: '{{ old('applies_to', $coupon->applies_to ?? 'all') }}', discountType: '{{ old('discount_type', $coupon->discount_type ?? 'percentage') }}' }">
    @if(!$coupon)
        <div class="md:col-span-2">
            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Coupon code <span class="text-red-500">*</span></label>
            <input type="text" name="code" id="code" value="{{ old('code') }}" required maxlength="64" placeholder="SAVE10"
                   class="w-full max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 uppercase">
            @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @else
        <div class="md:col-span-2 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 px-4 py-3">
            <span class="text-xs font-semibold uppercase text-slate-500">Code (fixed)</span>
            <p class="text-lg font-bold text-slate-900 dark:text-slate-100 tracking-wide">{{ $coupon->code }}</p>
        </div>
    @endif

    <div class="md:col-span-2">
        <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Title <span class="text-red-500">*</span></label>
        <input type="text" name="title" id="title" value="{{ old('title', $coupon->title ?? '') }}" required
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
        <textarea name="description" id="description" rows="2" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">{{ old('description', $coupon->description ?? '') }}</textarea>
    </div>

    <div>
        <label for="discount_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Discount type</label>
        <select name="discount_type" id="discount_type" x-model="discountType" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            <option value="percentage">Percentage (%)</option>
            <option value="fixed_amount">Fixed amount (AED)</option>
        </select>
    </div>

    <div>
        <label for="discount_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Discount value <span class="text-red-500">*</span></label>
        <input type="number" name="discount_value" id="discount_value" step="0.01" min="0" value="{{ old('discount_value', $coupon->discount_value ?? '') }}" required
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
        @error('discount_value')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="min_order_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Min order (AED)</label>
        <input type="number" name="min_order_amount" id="min_order_amount" step="0.01" min="0" value="{{ old('min_order_amount', $coupon->min_order_amount ?? 0) }}" required
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div x-show="discountType === 'percentage'" x-cloak>
        <label for="max_discount_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Max discount (AED)</label>
        <input type="number" name="max_discount_amount" id="max_discount_amount" step="0.01" min="0" value="{{ old('max_discount_amount', $coupon->max_discount_amount ?? '') }}"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div>
        <label for="starts_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Starts</label>
        <input type="date" name="starts_at" id="starts_at" value="{{ old('starts_at', optional($coupon?->starts_at)->format('Y-m-d')) }}"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div>
        <label for="ends_at" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ends</label>
        <input type="date" name="ends_at" id="ends_at" value="{{ old('ends_at', optional($coupon?->ends_at)->format('Y-m-d')) }}"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div>
        <label for="usage_limit" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Total usage limit</label>
        <input type="number" name="usage_limit" id="usage_limit" min="1" value="{{ old('usage_limit', $coupon->usage_limit ?? '') }}" placeholder="Unlimited"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div>
        <label for="usage_limit_per_user" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Per customer limit</label>
        <input type="number" name="usage_limit_per_user" id="usage_limit_per_user" min="1" value="{{ old('usage_limit_per_user', $coupon->usage_limit_per_user ?? '') }}" placeholder="Unlimited"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
    </div>

    <div class="md:col-span-2">
        <label for="applies_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Applies to</label>
        <select name="applies_to" id="applies_to" x-model="appliesTo" class="w-full max-w-md rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            <option value="all">All products</option>
            <option value="categories">Selected categories</option>
            <option value="services">Selected services</option>
        </select>
        @error('applies_to')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2" x-show="appliesTo === 'categories'" x-cloak>
        <label for="category_ids" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Categories</label>
        <select name="category_ids[]" id="category_ids" multiple size="6" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(in_array($cat->id, old('category_ids', $selectedCategoryIds)))>{{ $cat->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</p>
        @error('category_ids')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2" x-show="appliesTo === 'services'" x-cloak>
        <label for="service_ids" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Services</label>
        <select name="service_ids[]" id="service_ids" multiple size="6" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            @foreach($services as $svc)
                <option value="{{ $svc->id }}" @selected(in_array($svc->id, old('service_ids', $selectedServiceIds)))>{{ $svc->name }}</option>
            @endforeach
        </select>
        @error('service_ids')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2 flex items-center gap-2">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" id="is_active" value="1" @checked(filter_var(old('is_active', $coupon->is_active ?? true), FILTER_VALIDATE_BOOLEAN))
               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
        <label for="is_active" class="text-sm font-medium text-gray-700 dark:text-gray-300">Active (customers can use this coupon)</label>
    </div>
</div>
