<x-client-layout>
    <div class="mb-4 sm:mb-6">
        <a href="{{ route('client.services.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-900 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back to Services
        </a>
        <h1 class="text-lg sm:text-xl font-medium text-gray-900">{{ $category->name }}</h1>
        @if($category->description)
            <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $category->description }}</p>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        @forelse($category->products as $product)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                @if($product->image)
                    <img src="{{ $product->image_url ?? asset('media/' . $product->image) }}" alt="{{ $product->name }}" class="w-full h-40 rounded-lg object-cover border border-gray-200">
                @else
                    <div class="w-full h-40 rounded-lg bg-gray-100 flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                @endif
                <h3 class="mt-3 text-base font-semibold text-gray-900">{{ $product->name }}</h3>
                @if($product->description)
                    <p class="mt-1 text-sm text-gray-500 line-clamp-2">{{ Str::limit($product->description, 100) }}</p>
                @endif
                <p class="mt-2 text-sm font-medium text-gray-900">AED {{ number_format($product->price, 2) }}</p>
            </div>
        @empty
            <div class="col-span-full bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center">
                <p class="text-gray-500">No services in this category yet.</p>
            </div>
        @endforelse
    </div>
</x-client-layout>
