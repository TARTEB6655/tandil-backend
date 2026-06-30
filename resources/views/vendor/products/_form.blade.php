@php $p = $vendorProduct->product ?? null; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Name</label>
        <input type="text" name="name" value="{{ old('name', $p?->name) }}" required class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Category</label>
        <select name="category_id" class="mt-1 w-full rounded-lg border-gray-300">
            <option value="">— Select —</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(old('category_id', $p?->category_id) == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Price (AED)</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $vendorProduct->currentPrice?->price ?? $p?->price) }}" required class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Compare at price</label>
        <input type="number" step="0.01" min="0" name="compare_at_price" value="{{ old('compare_at_price', $vendorProduct->currentPrice?->compare_at_price ?? $p?->compare_at_price) }}" class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Stock</label>
        <input type="number" min="0" name="stock" value="{{ old('stock', $vendorProduct->inventory?->quantity ?? $p?->stock ?? 0) }}" class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Low stock threshold</label>
        <input type="number" min="0" name="low_stock_threshold" value="{{ old('low_stock_threshold', $vendorProduct->inventory?->low_stock_threshold ?? 5) }}" class="mt-1 w-full rounded-lg border-gray-300" />
    </div>
    <div>
        <label class="text-sm font-medium text-gray-700">Product status</label>
        <select name="status" class="mt-1 w-full rounded-lg border-gray-300">
            @foreach(['active', 'draft', 'archived'] as $st)
                <option value="{{ $st }}" @selected(old('status', $p?->status ?? 'active') === $st)>{{ ucfirst($st) }}</option>
            @endforeach
        </select>
    </div>
    @if(isset($vendorProduct->id))
        <div>
            <label class="text-sm font-medium text-gray-700">Listing status</label>
            <select name="vendor_product_status" class="mt-1 w-full rounded-lg border-gray-300">
                <option value="active" @selected(old('vendor_product_status', $vendorProduct->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('vendor_product_status', $vendorProduct->status) === 'inactive')>Inactive</option>
            </select>
        </div>
    @endif
    @if(!empty($services))
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Services (optional)</label>
        <select name="service_ids[]" multiple class="mt-1 w-full rounded-lg border-gray-300">
            @foreach($services as $service)
                <option value="{{ $service->id }}" @selected(collect(old('service_ids', $p?->services?->pluck('id') ?? []))->contains($service->id))>{{ $service->name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Description</label>
        <textarea name="description" rows="4" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $p?->description) }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label class="text-sm font-medium text-gray-700">Image</label>
        <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm" />
    </div>
</div>
