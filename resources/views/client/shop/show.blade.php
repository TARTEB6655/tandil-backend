<x-client-layout>
    @php
        $displayImages = $product->getDisplayImageList();
        $mainImageUrl = $displayImages[0]['url'] ?? null;
        $isVariable = ($product->product_type ?? 'simple') === 'variable' && $product->optionGroups->isNotEmpty();
    @endphp

    <div class="mb-6">
        <a href="{{ route('client.shop.index') }}" class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-indigo-600">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to shop
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border-l-4 border-green-400 p-4 rounded-md text-sm text-green-700">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4 rounded-md text-sm text-red-700">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        <div class="bg-white rounded-xl border border-gray-200 w-full max-w-md mx-auto lg:mx-0" style="max-width: 420px;">
            <div class="h-80 bg-gray-100 overflow-hidden rounded-t-xl" style="max-height: 420px;">
                @if($mainImageUrl)
                    <img id="productMainImage"
                         src="{{ $mainImageUrl }}"
                         alt="{{ $product->name }}"
                         class="w-full h-full object-cover transition-opacity duration-200">
                @else
                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                        <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14"/>
                        </svg>
                    </div>
                @endif
            </div>

            @if(count($displayImages) > 1)
                <div class="border-t border-gray-100">
                    <div class="p-4">
                        <div class="product-gallery-scroll">
                            <div class="product-gallery-track" id="productGalleryThumbs" role="listbox" aria-label="Product images">
                                @foreach($displayImages as $index => $img)
                                    <button type="button"
                                            role="option"
                                            aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                                            aria-label="View image {{ $index + 1 }}"
                                            data-gallery-url="{{ $img['url'] }}"
                                            class="product-gallery-thumb{{ $index === 0 ? ' is-active' : '' }}">
                                        <img src="{{ $img['url'] }}"
                                             alt="{{ $product->name }} — image {{ $index + 1 }}"
                                             class="gallery-thumb-img"
                                             loading="lazy"
                                             draggable="false">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="w-full max-w-xl" style="max-width: 520px;">
            @if($product->category)
                <p class="text-sm text-gray-500 mb-1">{{ $product->category->name }}</p>
            @endif
            <h1 class="text-2xl font-semibold text-gray-900 mb-2">{{ $product->name }}</h1>
            <p class="text-2xl font-bold text-indigo-600 mb-1">AED {{ number_format($product->price, 2) }}</p>
            <p class="text-sm text-gray-500 mb-4">
                Estimated delivery:
                <span class="font-medium text-gray-700">{{ ($estimatedShipping ?? 0) > 0 ? 'AED ' . number_format($estimatedShipping, 2) : 'Free' }}</span>
                @if($product->category)
                    <span class="text-gray-400">({{ $product->category->name }})</span>
                @endif
            </p>
            @if($product->description)
                <p class="text-sm text-gray-600 mb-6">{{ $product->description }}</p>
            @endif

            <form action="{{ route('client.cart.add') }}" method="POST" id="productDetailForm" onsubmit="return validateDetailOptions()">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if($isVariable)
                    <div class="space-y-4 mb-5">
                        @foreach($product->optionGroups as $group)
                            <div class="p-4 rounded-xl border border-gray-200 bg-gray-50" data-group-name="opt_{{ $group->id }}" data-required="{{ $group->is_required ? '1' : '0' }}">
                                <div class="flex items-center justify-between mb-1">
                                    <h3 class="text-sm font-semibold text-gray-800">{{ $group->name }}</h3>
                                    <span class="text-xs px-2 py-0.5 rounded-full {{ $group->is_required ? 'bg-red-50 text-red-600' : 'bg-gray-200 text-gray-600' }}">
                                        {{ $group->is_required ? 'Required' : 'Optional' }}
                                    </span>
                                </div>
                                @if($group->subtitle)
                                    <p class="text-xs text-gray-500 mb-3">{{ $group->subtitle }}</p>
                                @endif

                                <div class="space-y-2">
                                    @foreach($group->options as $opt)
                                        <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 bg-white cursor-pointer hover:border-indigo-300">
                                            <input type="{{ $group->input_type === 'multi' ? 'checkbox' : 'radio' }}"
                                                   name="opt_{{ $group->id }}{{ $group->input_type === 'multi' ? '[]' : '' }}"
                                                   value="{{ $opt->id }}"
                                                   class="text-indigo-600 focus:ring-indigo-500">
                                            @if($opt->image_url)
                                                <img src="{{ $opt->image_url }}" class="w-11 h-11 rounded object-cover shrink-0" alt="{{ $opt->label }}">
                                            @endif
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-800">{{ $opt->label }}</p>
                                                @if($opt->subtitle)
                                                    <p class="text-xs text-gray-500">{{ $opt->subtitle }}</p>
                                                @endif
                                            </div>
                                            <p class="text-sm font-semibold {{ ((float)$opt->price_modifier) > 0 ? 'text-green-700' : 'text-gray-500' }}">
                                                {{ ((float)$opt->price_modifier) > 0 ? '+' . number_format((float)$opt->price_modifier, 2) . ' AED' : 'Free' }}
                                            </p>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex items-center gap-3 mb-4">
                    <label class="text-sm text-gray-600">Qty:</label>
                    <input type="number" name="quantity" value="1" min="1"
                           max="{{ $product->stock > 0 ? $product->stock : 1 }}"
                           class="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                           {{ $product->stock <= 0 ? 'disabled' : '' }}>
                </div>

                <button type="submit"
                        class="w-full lg:w-auto px-8 py-3 rounded-xl font-semibold transition-colors
                               {{ $product->stock <= 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-indigo-600 text-white hover:bg-indigo-700' }}"
                        {{ $product->stock <= 0 ? 'disabled' : '' }}>
                    {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </button>
            </form>
        </div>
    </div>

    @push('styles')
    <style>
        .product-gallery-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            height: 4.75rem;
        }
        .product-gallery-track {
            display: flex;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.75rem;
            padding: 4px 2px;
            width: max-content;
            height: 4.75rem;
            box-sizing: border-box;
        }
        #productGalleryThumbs .product-gallery-thumb {
            flex-shrink: 0;
            box-sizing: border-box;
            width: 4.25rem;
            height: 4.25rem;
            min-width: 4.25rem;
            min-height: 4.25rem;
            padding: 0;
            margin: 0;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            background: #fff;
            cursor: pointer;
            line-height: 0;
            box-shadow: none !important;
            outline: none !important;
            transition: none !important;
            transform: none !important;
            appearance: none;
            -webkit-appearance: none;
        }
        #productGalleryThumbs .product-gallery-thumb.is-active {
            border-color: #6366f1;
        }
        #productGalleryThumbs .product-gallery-thumb:hover,
        #productGalleryThumbs .product-gallery-thumb:focus,
        #productGalleryThumbs .product-gallery-thumb:focus-visible,
        #productGalleryThumbs .product-gallery-thumb:active {
            outline: none !important;
            box-shadow: none !important;
            transform: none !important;
            padding: 0;
            margin: 0;
        }
        #productGalleryThumbs .product-gallery-thumb:not(.is-active):hover,
        #productGalleryThumbs .product-gallery-thumb:not(.is-active):focus,
        #productGalleryThumbs .product-gallery-thumb:not(.is-active):active {
            border-color: #e5e7eb;
        }
        #productGalleryThumbs .product-gallery-thumb.is-active:hover,
        #productGalleryThumbs .product-gallery-thumb.is-active:focus,
        #productGalleryThumbs .product-gallery-thumb.is-active:active {
            border-color: #6366f1;
        }
        #productGalleryThumbs .gallery-thumb-img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0.375rem;
            pointer-events: none;
            user-select: none;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
    (function () {
        var main = document.getElementById('productMainImage');
        var thumbs = document.getElementById('productGalleryThumbs');
        if (!main || !thumbs) return;

        thumbs.querySelectorAll('.product-gallery-thumb').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-gallery-url');
                if (!url) return;
                main.src = url;
                thumbs.querySelectorAll('.product-gallery-thumb').forEach(function (b) {
                    b.classList.remove('is-active');
                    b.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');
                btn.blur();
            });
        });
    })();

    function validateDetailOptions() {
        var form = document.getElementById('productDetailForm');
        if (!form) return true;

        form.querySelectorAll('.injected-opt').forEach(function (el) { el.remove(); });

        var valid = true;
        document.querySelectorAll('[data-group-name][data-required="1"]').forEach(function (groupEl) {
            var gname = groupEl.getAttribute('data-group-name');
            var radios = groupEl.querySelectorAll('input[type="radio"][name="' + gname + '"]');
            var checks = groupEl.querySelectorAll('input[type="checkbox"][name="' + gname + '[]"]');
            var hasSelected = false;

            if (radios.length > 0) {
                hasSelected = !!groupEl.querySelector('input[type="radio"][name="' + gname + '"]:checked');
            } else if (checks.length > 0) {
                hasSelected = !!groupEl.querySelector('input[type="checkbox"][name="' + gname + '[]"]:checked');
            }

            if (!hasSelected) {
                valid = false;
            }
        });

        if (!valid) {
            alert('Please select all required options.');
            return false;
        }

        document.querySelectorAll('[name^="opt_"]:checked').forEach(function (el) {
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
