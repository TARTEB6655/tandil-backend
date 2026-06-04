<x-client-layout>
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">Shop</h1>
            <p class="mt-0.5 text-sm text-gray-500">
                @if($selectedCategory) {{ $selectedCategory->name }} @else All products @endif
            </p>
        </div>
        <a href="{{ route('client.cart.index') }}"
           class="relative inline-flex items-center gap-2 px-4 py-2 bg-gray-900 text-white rounded-lg hover:bg-gray-800 transition-colors text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Cart
            @php $cartCount = \App\Models\Cart::where('user_id', auth()->id())->sum('quantity'); @endphp
            @if($cartCount > 0)
                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center">{{ $cartCount }}</span>
            @endif
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-md text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="flex gap-6">
        {{-- Category sidebar --}}
        <aside class="hidden lg:block w-52 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 p-4 sticky top-6">
                <h2 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Categories</h2>
                <ul class="space-y-1">
                    <li>
                        <a href="{{ route('client.shop.index') }}"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                  {{ !request('category_id') ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                            All products
                        </a>
                    </li>
                    @foreach($categories as $cat)
                        <li>
                            <a href="{{ route('client.shop.index', ['category_id' => $cat->id]) }}"
                               class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                      {{ request('category_id') == $cat->id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span>{{ $cat->name }}</span>
                                @if($cat->products_count > 0)
                                    <span class="text-xs text-gray-400">{{ $cat->products_count }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>

        {{-- Mobile category scroll --}}
        <div class="lg:hidden -mx-4 px-4 mb-4 overflow-x-auto">
            <div class="flex gap-2 pb-2 min-w-max">
                <a href="{{ route('client.shop.index') }}"
                   class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors
                          {{ !request('category_id') ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400' }}">
                    All
                </a>
                @foreach($categories as $cat)
                    <a href="{{ route('client.shop.index', ['category_id' => $cat->id]) }}"
                       class="px-3 py-1.5 rounded-full text-sm font-medium border whitespace-nowrap transition-colors
                              {{ request('category_id') == $cat->id ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-400' }}">
                        {{ $cat->name }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Products grid --}}
        <div class="flex-1 min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                @forelse($products as $product)
                    @php
                        // Use centralized model URL accessor (same behavior as admin/products API).
                        $imgUrl = $product->getImageUrl();
                        $isVariable = ($product->product_type ?? 'simple') === 'variable';
                        $hasGroups  = $isVariable && $product->optionGroups->isNotEmpty();
                    @endphp
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow group flex flex-col h-full cursor-pointer"
                         onclick="window.location='{{ route('client.shop.show', $product->id) }}'">
                        {{-- Image --}}
                        <div class="relative aspect-[4/3] bg-gray-100 overflow-hidden shrink-0">
                            @if($imgUrl)
                                <img src="{{ $imgUrl }}" alt="{{ $product->name }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                            @endif
                            @if($product->stock <= 0 && !$isVariable)
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                                    <span class="text-white font-medium text-sm">Out of Stock</span>
                                </div>
                            @endif
                            @if($isVariable)
                                <span class="absolute top-2 left-2 bg-indigo-600 text-white text-xs font-medium px-2 py-0.5 rounded">Variable</span>
                            @endif
                        </div>

                        <div class="p-4 flex flex-col flex-1 min-h-[190px]">
                            @if($product->category)
                                <p class="text-xs text-gray-400 mb-1">{{ $product->category->name }}</p>
                            @endif
                            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-2">{{ $product->name }}</h3>

                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-base font-bold text-indigo-600">AED {{ number_format($product->price, 2) }}</p>
                                @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                    <p class="text-xs text-gray-400 line-through">AED {{ number_format($product->compare_at_price, 2) }}</p>
                                @endif
                            </div>
                            @php
                                $cat = $product->category;
                                $deliveryFee = $cat && $cat->shipping_cost !== null ? (float) $cat->shipping_cost : null;
                            @endphp
                            <p class="text-xs text-gray-500 mb-3">
                                Delivery: {{ $deliveryFee !== null ? 'AED ' . number_format($deliveryFee, 2) : 'shop default' }}
                                @if($cat && $cat->tax_percentage !== null)
                                    · Tax {{ number_format($cat->tax_percentage, 0) }}%
                                @endif
                            </p>

                            <div class="mt-auto">
                                <a href="{{ route('client.shop.show', $product->id) }}"
                                   onclick="event.stopPropagation()"
                                   class="block w-full py-2.5 px-4 rounded-lg text-sm font-medium text-center transition-colors
                                          {{ $product->stock <= 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed pointer-events-none' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}">
                                    {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-16">
                        <svg class="w-14 h-14 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <p class="text-gray-400">No products available</p>
                        @if(request('category_id'))
                            <a href="{{ route('client.shop.index') }}" class="text-indigo-500 text-sm mt-1 block hover:underline">View all products</a>
                        @endif
                    </div>
                @endforelse
            </div>

            @if($products->hasPages())
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </div>
    </div>

</x-client-layout>
