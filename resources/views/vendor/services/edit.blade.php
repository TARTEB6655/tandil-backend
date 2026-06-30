<x-vendor-layout>
    <x-dashboard.page-header title="Edit Service" :subtitle="$service->name" />
    <form method="POST" action="{{ route('vendor.services.update', $service) }}" enctype="multipart/form-data" class="max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')
        <div>
            <label class="text-sm font-medium text-gray-700">Name *</label>
            <input type="text" name="name" value="{{ old('name', $service->name) }}" required class="mt-1 w-full rounded-lg border-gray-300" />
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Category</label>
            <select name="category_id" class="mt-1 w-full rounded-lg border-gray-300">
                <option value="">— Optional —</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $service->category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Description</label>
            <textarea name="description" rows="3" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $service->description) }}</textarea>
        </div>
        <div>
            <label class="text-sm font-medium text-gray-700">Image</label>
            <input type="file" name="image" accept="image/*" class="mt-1 w-full text-sm" />
        </div>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active)) /> Active</label>
        <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Update Service</button>
    </form>
</x-vendor-layout>
