@php
    use Illuminate\Support\Facades\Storage;
@endphp
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
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden hover:shadow-md transition-shadow group flex flex-col">
                        {{-- Image --}}
                        <div class="relative h-52 bg-gray-100 overflow-hidden shrink-0">
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

                        <div class="p-4 flex flex-col flex-1">
                            @if($product->category)
                                <p class="text-xs text-gray-400 mb-1">{{ $product->category->name }}</p>
                            @endif
                            <h3 class="text-sm font-semibold text-gray-900 line-clamp-2 mb-2">{{ $product->name }}</h3>

                            <div class="flex items-center gap-2 mb-3">
                                <p class="text-base font-bold text-indigo-600">AED {{ number_format($product->price, 2) }}</p>
                                @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                    <p class="text-xs text-gray-400 line-through">AED {{ number_format($product->compare_at_price, 2) }}</p>
                                @endif
                            </div>

                            <div class="mt-auto">
                                @if($hasGroups)
                                    {{-- Variable: open option-picker modal --}}
                                    <button type="button"
                                            onclick="openVariableModal({{ $product->id }})"
                                            class="w-full bg-indigo-600 text-white py-2.5 px-4 rounded-lg hover:bg-indigo-700 transition-colors text-sm font-medium">
                                        Select Options
                                    </button>
                                @else
                                    {{-- Simple: direct add-to-cart --}}
                                    <form action="{{ route('client.cart.add') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                                        <div class="flex items-center gap-2 mb-2">
                                            <label class="text-xs text-gray-500">Qty:</label>
                                            <input type="number" name="quantity" value="1" min="1"
                                                   max="{{ $product->stock > 0 ? $product->stock : 1 }}"
                                                   class="w-16 px-2 py-1 border border-gray-300 rounded text-sm"
                                                   {{ $product->stock <= 0 ? 'disabled' : '' }}>
                                        </div>
                                        <button type="submit"
                                                class="w-full py-2.5 px-4 rounded-lg text-sm font-medium transition-colors
                                                       {{ $product->stock <= 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}"
                                                {{ $product->stock <= 0 ? 'disabled' : '' }}>
                                            {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                                        </button>
                                    </form>
                                @endif
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

    {{-- Variable product option-picker modals --}}
    @foreach($products as $product)
        @php
            $isVariable = ($product->product_type ?? 'simple') === 'variable';
            $hasGroups  = $isVariable && $product->optionGroups->isNotEmpty();
        @endphp
        @if($hasGroups)
            <div id="varModal-{{ $product->id }}"
                 class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 hidden"
                 onclick="if(event.target===this) closeVariableModal({{ $product->id }})">
                <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
                    {{-- Modal header --}}
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $product->name }}</h3>
                            <p class="text-sm text-indigo-600 font-medium mt-0.5" id="modalPrice-{{ $product->id }}">
                                AED {{ number_format($product->price, 2) }}
                            </p>
                        </div>
                        <button onclick="closeVariableModal({{ $product->id }})" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                    </div>

                    {{-- Modal scrollable body --}}
                    <div class="overflow-y-auto flex-1 px-5 py-4 space-y-6">
                        @foreach($product->optionGroups as $group)
                            <div data-group-name="opt_{{ $group->id }}" data-required="{{ $group->is_required ? '1' : '0' }}">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-semibold text-gray-800">{{ $group->name }}</h4>
                                    <span class="text-xs px-2 py-0.5 rounded-full
                                                 {{ $group->is_required ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $group->is_required ? 'Required' : 'Optional' }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mb-3">
                                    {{ $group->is_required ? 'Required' : 'Optional' }} &mdash;
                                    {{ $group->input_type === 'multi' ? 'Select any' : 'Select one' }}
                                </p>

                                @if($group->input_type === 'multi')
                                    {{-- Multi-select: checkboxes --}}
                                    <div class="space-y-2">
                                        @foreach($group->options as $opt)
                                            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 cursor-pointer hover:border-indigo-300 transition-colors">
                                                <input type="checkbox"
                                                       name="opt_{{ $group->id }}[]"
                                                       value="{{ $opt->id }}"
                                                       data-price="{{ $opt->price_modifier }}"
                                                       data-product="{{ $product->id }}"
                                                       onchange="recalcPrice({{ $product->id }}, {{ (float)$product->price }})"
                                                       class="rounded text-indigo-600 focus:ring-indigo-500">
                                                @if($opt->image_url)
                                                    <img src="{{ $opt->image_url }}" class="w-10 h-10 rounded object-cover shrink-0" alt="{{ $opt->label }}">
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-800">{{ $opt->label }}</p>
                                                    @if($opt->price_modifier != 0)
                                                        <p class="text-xs text-gray-500">{{ $opt->price_modifier > 0 ? '+' : '' }}{{ number_format($opt->price_modifier, 2) }} AED</p>
                                                    @else
                                                        <p class="text-xs text-green-600">Free</p>
                                                    @endif
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    {{-- Single select: radio-style image cards --}}
                                    <div class="flex flex-wrap gap-2">
                                        @foreach($group->options as $opt)
                                            <label class="relative cursor-pointer">
                                                <input type="radio"
                                                       name="opt_{{ $group->id }}"
                                                       value="{{ $opt->id }}"
                                                       data-price="{{ $opt->price_modifier }}"
                                                       data-product="{{ $product->id }}"
                                                       onchange="recalcPrice({{ $product->id }}, {{ (float)$product->price }})"
                                                       class="sr-only peer">
                                                <div class="flex flex-col items-center gap-1 p-2 rounded-xl border-2 border-gray-200 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 transition-all min-w-[80px]">
                                                    @if($opt->image_url)
                                                        <img src="{{ $opt->image_url }}" class="w-12 h-12 rounded object-cover" alt="{{ $opt->label }}">
                                                    @else
                                                        <div class="w-12 h-12 rounded bg-gray-100 flex items-center justify-center">
                                                            <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5"/></svg>
                                                        </div>
                                                    @endif
                                                    <p class="text-xs font-medium text-center text-gray-700 leading-tight">{{ $opt->label }}</p>
                                                    @if($opt->price_modifier != 0)
                                                        <p class="text-xs text-gray-500">{{ $opt->price_modifier > 0 ? '+' : '' }}{{ number_format(abs($opt->price_modifier), 2) }}</p>
                                                    @else
                                                        <p class="text-xs text-green-600">Free</p>
                                                    @endif
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Modal footer --}}
                    <div class="px-5 py-4 border-t border-gray-100 shrink-0">
                        <form action="{{ route('client.cart.add') }}" method="POST"
                              id="varForm-{{ $product->id }}"
                              onsubmit="return validateVariableForm({{ $product->id }})">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <div class="flex items-center gap-3 mb-3">
                                <label class="text-sm text-gray-600">Qty:</label>
                                <input type="number" name="quantity" value="1" min="1"
                                       class="w-20 px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                            </div>
                            {{-- Hidden fields for selected options are injected by JS --}}
                            <button type="submit"
                                    class="w-full bg-indigo-600 text-white py-3 px-4 rounded-xl hover:bg-indigo-700 transition-colors font-semibold">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    @push('scripts')
    <script>
    function openVariableModal(productId) {
        document.getElementById('varModal-' + productId)?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function closeVariableModal(productId) {
        document.getElementById('varModal-' + productId)?.classList.add('hidden');
        document.body.style.overflow = '';
    }
    function recalcPrice(productId, basePrice) {
        var extra = 0;
        // radios
        document.querySelectorAll('[data-product="' + productId + '"][type="radio"]:checked').forEach(function(el) {
            extra += parseFloat(el.dataset.price) || 0;
        });
        // checkboxes
        document.querySelectorAll('[data-product="' + productId + '"][type="checkbox"]:checked').forEach(function(el) {
            extra += parseFloat(el.dataset.price) || 0;
        });
        var el = document.getElementById('modalPrice-' + productId);
        if (el) el.textContent = 'AED ' + (basePrice + extra).toFixed(2);
    }
    function validateVariableForm(productId) {
        var form = document.getElementById('varForm-' + productId);
        var modal = document.getElementById('varModal-' + productId);
        if (!form || !modal) return true;

        // Check required single-select groups only
        var valid = true;
        modal.querySelectorAll('[data-group-name][data-required="1"]').forEach(function(groupEl) {
            var gname = groupEl.getAttribute('data-group-name');
            var radios = groupEl.querySelectorAll('input[type="radio"][name="' + gname + '"]');
            if (radios.length > 0 && !groupEl.querySelector('input[type="radio"][name="' + gname + '"]:checked')) {
                alert('Please select an option for all required groups.');
                valid = false;
            }
        });
        if (!valid) return false;

        // Inject selected option IDs as hidden inputs
        form.querySelectorAll('.injected-opt').forEach(function(el) { el.remove(); });
        modal.querySelectorAll('[name^="opt_"]:checked').forEach(function(el) {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'option_ids[]';
            hidden.value = el.value;
            hidden.className = 'injected-opt';
            form.appendChild(hidden);
        });
        return true;
    }
    </script>
    @endpush
</x-client-layout>
