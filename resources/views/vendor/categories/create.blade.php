<x-vendor-layout>
    <x-dashboard.page-header title="Add Category" subtitle="Create a category for your products." />
    <form method="POST" action="{{ route('vendor.categories.store') }}" enctype="multipart/form-data" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        <div>
            <label class="text-sm font-medium text-gray-700">Name *</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border-gray-300" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description') }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-sm font-medium text-gray-700">Shipping cost (AED)</label>
                <input type="number" step="0.01" min="0" name="shipping_cost" value="{{ old('shipping_cost', 0) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700">Tax %</label>
                <input type="number" step="0.01" min="0" max="100" name="tax_percentage" value="{{ old('tax_percentage', 0) }}" class="mt-1 w-full rounded-lg border-gray-300" />
            </div>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Image</label>
            <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" checked /> Active</label>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save Category</button>
    </form>
</x-vendor-layout>
