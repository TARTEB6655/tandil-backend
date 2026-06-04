<x-admin-layout>
    <div class="max-w-xl space-y-6">
        <x-admin.marketplace-nav />
        <h1 class="text-xl font-semibold">Edit product</h1>
        <form method="POST" action="{{ route('admin.marketplace.products.update', $vendorProduct) }}" enctype="multipart/form-data" class="space-y-4 bg-white dark:bg-gray-800 rounded-xl border p-6">
            @csrf @method('PUT')
            <div><label class="text-sm font-medium">Name</label><input name="name" value="{{ old('name', $vendorProduct->product?->name) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Category</label>
                <select name="category_id" class="mt-1 w-full rounded-lg border-gray-300">
                    <option value="">—</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id', $vendorProduct->product?->category_id)==$cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="text-sm font-medium">Price (AED)</label><input name="price" type="number" step="0.01" value="{{ old('price', $vendorProduct->product?->price) }}" required class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Stock</label><input name="stock" type="number" value="{{ old('stock', $vendorProduct->inventory?->quantity) }}" class="mt-1 w-full rounded-lg border-gray-300" /></div>
            <div><label class="text-sm font-medium">Status</label>
                <select name="status" class="mt-1 w-full rounded-lg border-gray-300">
                    @foreach(['active','draft','archived'] as $s)
                        <option value="{{ $s }}" @selected(old('status', $vendorProduct->product?->status)===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="text-sm font-medium">Description</label><textarea name="description" rows="4" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $vendorProduct->product?->description) }}</textarea></div>
            <div><label class="text-sm font-medium">Image</label><input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm" /></div>
            <button class="px-4 py-2 bg-gray-900 text-white text-sm rounded-lg">Save</button>
        </form>
    </div>
</x-admin-layout>
