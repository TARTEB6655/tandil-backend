<x-admin-layout>
    <h1 class="text-xl font-medium text-gray-900 mb-6">
            Product Details
        </h1>

    <div class="space-y-6">
        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-2 gap-6">
                @if($product->image)
                <div>
                    <img src="{{ asset('storage/' . $product->image) }}" alt="{{ $product->name }}" class="w-full rounded-lg">
                </div>
                @endif

                <div>
                    <h2 class="text-lg font-medium text-gray-900 mb-2">{{ $product->name }}</h2>
                    <p class="text-lg font-medium text-indigo-600 mb-4">AED {{ number_format($product->price, 2) }}</p>
                    <p class="text-sm text-gray-500 mb-2">Category: {{ $product->category->name ?? 'No Category' }}</p>
                    @if($product->description)
                        <p class="text-sm text-gray-700 mt-4">{{ $product->description }}</p>
                    @endif
                </div>
            </div>

            <div class="pt-4 flex gap-4">
                <a href="{{ route('admin.products.edit', $product) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Edit</a>
                <a href="{{ route('admin.products.index') }}" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">Back to Products</a>
            </div>
        </div>
    </div>
</x-admin-layout>

