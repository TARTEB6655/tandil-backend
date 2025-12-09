@php
    use Illuminate\Support\Facades\Storage;
@endphp
<x-client-layout>
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-medium text-gray-900">Shop</h1>
                <p class="mt-1 text-sm text-gray-500">Browse and purchase products.</p>
            </div>
            <a href="{{ route('client.cart.index') }}" class="relative inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Cart
                @php
                    $cartCount = \App\Models\Cart::where('user_id', auth()->id())->sum('quantity');
                @endphp
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">{{ $cartCount }}</span>
                @endif
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-md">
            <p class="text-sm text-green-700">{{ session('success') }}</p>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
            <p class="text-sm text-red-700">{{ session('error') }}</p>
        </div>
    @endif

    <!-- Products Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        @forelse($products as $product)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300 group">
                <!-- Product Image -->
                <div class="relative h-56 bg-gray-100 overflow-hidden">
                    @if($product->image)
                        <img src="{{ Storage::disk('public')->exists($product->image) ? asset('storage/' . $product->image) : (str_starts_with($product->image, 'http') ? $product->image : asset('images/placeholder.png')) }}" 
                             alt="{{ $product->name }}" 
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                    @else
                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200">
                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                    @if($product->stock <= 0)
                        <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                            <span class="text-white font-semibold">Out of Stock</span>
                        </div>
                    @endif
                </div>
                
                <div class="p-4">
                    @if($product->category)
                        <p class="text-xs text-gray-500 mb-1">{{ $product->category->name }}</p>
                    @endif
                    <h3 class="text-base font-semibold text-gray-900 mb-2 line-clamp-2">{{ $product->name }}</h3>
                    
                    <div class="flex items-center gap-2 mb-3">
                        <p class="text-lg font-bold text-indigo-600">AED {{ number_format($product->price, 2) }}</p>
                        @if($product->compare_at_price && $product->compare_at_price > $product->price)
                            <p class="text-sm text-gray-500 line-through">AED {{ number_format($product->compare_at_price, 2) }}</p>
                        @endif
                    </div>
                    
                    <form action="{{ route('client.cart.add') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <div class="flex items-center gap-2 mb-3">
                            <label class="text-sm text-gray-600">Qty:</label>
                            <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock }}" 
                                   class="w-20 px-2 py-1 border border-gray-300 rounded-md text-sm" 
                                   {{ $product->stock <= 0 ? 'disabled' : '' }}>
                        </div>
                        <button type="submit" 
                                class="w-full bg-indigo-600 text-white py-2.5 px-4 rounded-lg hover:bg-indigo-700 transition-colors font-medium disabled:bg-gray-400 disabled:cursor-not-allowed"
                                {{ $product->stock <= 0 ? 'disabled' : '' }}>
                            {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <p class="text-gray-500 text-lg">No products available</p>
            </div>
        @endforelse
    </div>

    @if($products->hasPages())
        <div class="mt-6">
            {{ $products->links() }}
        </div>
    @endif
</x-client-layout>

