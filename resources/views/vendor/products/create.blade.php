<x-vendor-layout>
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900">Add Product</h1>
        <p class="mt-1 text-sm text-gray-500"><a href="{{ route('vendor.products.index') }}" class="text-indigo-600 hover:underline">← Back to products</a></p>
    </div>

    <form method="POST" action="{{ route('vendor.products.store') }}" enctype="multipart/form-data" class="max-w-3xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        @csrf
        @php $vendorProduct = new \App\Models\VendorProduct; @endphp
        @include('vendor.products._form')
        <div class="mt-6 flex gap-3">
            <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Create Product</button>
            <a href="{{ route('vendor.products.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
        </div>
    </form>
</x-vendor-layout>
