<x-vendor-layout>
    <x-dashboard.page-header title="Business Categories" subtitle="Step 2 — select the categories you sell in." />

    <form method="POST" action="{{ route('vendor.onboarding.categories.update') }}" class="max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @method('PUT')
        @php $selected = old('category_ids', $vendor->categories->pluck('id')->all()); @endphp
        <div class="grid gap-2 sm:grid-cols-2">
            @forelse($categories as $category)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                    <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selected)) class="rounded border-gray-300 text-indigo-600" />
                    <span>{{ $category->name }}</span>
                </label>
            @empty
                <p class="text-sm text-gray-500 sm:col-span-2">No active categories available. Contact support.</p>
            @endforelse
        </div>
        <div class="mt-6 flex gap-3">
            <a href="{{ route('vendor.onboarding.profile') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Back</a>
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Save & continue</button>
        </div>
    </form>
</x-vendor-layout>
