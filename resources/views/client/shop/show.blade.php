<x-client-layout>
    @php
        $displayImages = $product->getDisplayImageList();
        $mainImageUrl = $displayImages[0]['url'] ?? null;
        $isVariable = ($product->product_type ?? 'simple') === 'variable' && $product->optionGroups->isNotEmpty();
        $inStock = (int) $product->stock > 0;
        $lowStock = $inStock && (int) $product->stock <= 5;
        $hasComparePrice = $product->compare_at_price && (float) $product->compare_at_price > (float) $product->price;
        $sku = trim((string) ($product->sku ?? ''));
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

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 xl:gap-14 items-start">
        <div class="product-gallery-card bg-white rounded-2xl border border-gray-200 shadow-sm w-full max-w-lg mx-auto lg:mx-0 lg:sticky lg:top-6">
            <div class="product-main-image-wrap rounded-t-xl">
                @if($mainImageUrl)
                    <img id="productMainImage"
                         src="{{ $mainImageUrl }}"
                         alt="{{ $product->name }}"
                         width="420"
                         height="420"
                         class="product-main-image">
                @else
                    <div class="product-main-image-placeholder flex items-center justify-center text-gray-300">
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

        <div class="product-purchase-panel w-full min-w-0 lg:max-w-xl">
            {{-- Product header: category → title → price → meta → description --}}
            <header class="product-header mb-6">
                @if($product->category)
                    <a href="{{ route('client.shop.index', ['category_id' => $product->category->id]) }}"
                       class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 transition-colors mb-3">
                        {{ $product->category->name }}
                    </a>
                @endif

                <h1 class="product-title text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 leading-tight">
                    {{ $product->name }}
                </h1>

                <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <p class="text-3xl sm:text-[2rem] font-bold text-gray-900 tabular-nums">
                        AED {{ number_format($product->price, 2) }}
                    </p>
                    @if($hasComparePrice)
                        <p class="text-lg text-gray-400 line-through tabular-nums">
                            AED {{ number_format((float) $product->compare_at_price, 2) }}
                        </p>
                    @endif
                </div>

                <ul class="product-meta mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-600">
                    @if($sku !== '')
                        <li class="inline-flex items-center gap-1.5">
                            <span class="text-gray-400">SKU</span>
                            <span class="font-mono text-xs font-medium text-gray-800 bg-gray-100 px-2 py-0.5 rounded">{{ $sku }}</span>
                        </li>
                    @endif
                    <li class="inline-flex items-center gap-1.5">
                        @if($inStock)
                            <span class="h-2 w-2 rounded-full {{ $lowStock ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                            <span class="{{ $lowStock ? 'text-amber-700 font-medium' : 'text-emerald-700 font-medium' }}">
                                @if($lowStock)
                                    Only {{ $product->stock }} left in stock
                                @else
                                    In stock ({{ $product->stock }} available)
                                @endif
                            </span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-red-500"></span>
                            <span class="text-red-600 font-medium">Out of stock</span>
                        @endif
                    </li>
                    <li class="inline-flex items-center gap-1.5 text-gray-500">
                        <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                        </svg>
                        <span>
                            Delivery
                            <span class="font-medium text-gray-800">{{ ($estimatedShipping ?? 0) > 0 ? 'AED ' . number_format($estimatedShipping, 2) : 'Free' }}</span>
                        </span>
                    </li>
                </ul>

                @if($product->description)
                    <div class="product-description mt-5 pt-5 border-t border-gray-100">
                        <p class="text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ $product->description }}</p>
                    </div>
                @endif
            </header>

            <form action="{{ route('client.cart.add') }}" method="POST" id="productDetailForm" class="product-form" onsubmit="return validateDetailOptions()">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if($isVariable)
                    <section class="product-options mb-6 pt-6 border-t border-gray-200" aria-label="Product options">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Customize your order</p>
                        <div class="space-y-5">
                            @foreach($product->optionGroups as $group)
                                <div class="option-group-card rounded-2xl border border-gray-200 bg-white p-4 sm:p-5 shadow-sm transition-colors duration-200"
                                     data-group-name="opt_{{ $group->id }}"
                                     data-required="{{ $group->is_required ? '1' : '0' }}"
                                     data-group-label="{{ $group->name }}">
                                    <div class="mb-3">
                                        <h3 class="text-sm font-semibold text-gray-900">{{ $group->name }}</h3>
                                        @if($group->subtitle)
                                            <p class="mt-0.5 text-xs text-gray-500">{{ $group->subtitle }}</p>
                                        @endif
                                    </div>

                                    <div class="space-y-2.5 option-group-options">
                                        @foreach($group->options as $opt)
                                            <label class="option-choice flex items-center gap-3 p-3 sm:p-3.5 rounded-xl border-2 border-gray-200 bg-gray-50/50 cursor-pointer transition-all duration-150 hover:border-gray-300 hover:bg-white">
                                                <input type="{{ $group->input_type === 'multi' ? 'checkbox' : 'radio' }}"
                                                       name="opt_{{ $group->id }}{{ $group->input_type === 'multi' ? '[]' : '' }}"
                                                       value="{{ $opt->id }}"
                                                       class="h-4 w-4 shrink-0 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                                @if($opt->image_url)
                                                    <img src="{{ $opt->image_url }}" class="h-12 w-12 sm:h-14 sm:w-14 rounded-lg object-cover shrink-0 ring-1 ring-gray-200" alt="{{ $opt->label }}">
                                                @endif
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900">{{ $opt->label }}</p>
                                                    @if($opt->subtitle)
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ $opt->subtitle }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 text-sm font-semibold tabular-nums {{ ((float)$opt->price_modifier) > 0 ? 'text-gray-900' : 'text-gray-500' }}">
                                                    {{ ((float)$opt->price_modifier) > 0 ? '+' . number_format((float)$opt->price_modifier, 2) . ' AED' : 'Free' }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if($group->is_required)
                                        <p class="option-group-error hidden mt-3 text-xs leading-snug text-red-600" role="alert" aria-live="polite"></p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <div class="product-actions mt-6 pt-6 border-t border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-end gap-4">
                        <div class="shrink-0">
                            <label for="productQty" class="block text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Quantity</label>
                            <input type="number" name="quantity" id="productQty" value="1" min="1"
                                   max="{{ $product->stock > 0 ? $product->stock : 1 }}"
                                   class="w-28 px-4 py-2.5 border border-gray-300 rounded-xl text-sm font-medium text-gray-900 focus:border-indigo-500 focus:ring-indigo-500"
                                   {{ $product->stock <= 0 ? 'disabled' : '' }}>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p id="addToCartFormError" class="hidden mb-2 text-xs text-red-600" role="alert" aria-live="polite"></p>
                            <button type="submit" id="addToCartBtn"
                                    class="w-full inline-flex items-center justify-center gap-2 px-8 py-3.5 rounded-xl text-base font-semibold shadow-sm transition-all
                                           {{ $product->stock <= 0 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-gray-900 text-white hover:bg-gray-800 active:scale-[0.99]' }}"
                                    {{ $product->stock <= 0 ? 'disabled' : '' }}>
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                                {{ $product->stock <= 0 ? 'Out of stock' : 'Add to cart' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('styles')
    <style>
        .product-main-image-wrap {
            position: relative;
            width: 100%;
            height: 420px;
            min-height: 420px;
            max-height: 420px;
            flex-shrink: 0;
            overflow: hidden;
            background: #f3f4f6;
        }
        .product-main-image-wrap .product-main-image,
        .product-main-image-wrap .product-main-image-placeholder {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        .product-main-image-wrap .product-main-image {
            object-fit: cover;
            object-position: center;
            display: block;
        }
        .product-gallery-card {
            flex-shrink: 0;
        }
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
        .option-group-card.is-invalid {
            border-color: #f87171;
            background-color: #fef2f2;
            box-shadow: 0 0 0 1px rgba(248, 113, 113, 0.35);
        }
        .option-group-error:not(.hidden) {
            display: block;
            padding-top: 0.5rem;
            border-top: 1px solid #fecaca;
        }
        .option-choice:has(input:checked) {
            border-color: #4f46e5;
            background-color: #eef2ff;
            box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.15);
        }
        .option-choice:has(input:focus-visible) {
            outline: 2px solid #6366f1;
            outline-offset: 2px;
        }
        @media (min-width: 1024px) {
            .product-main-image-wrap {
                height: 480px;
                min-height: 480px;
                max-height: 480px;
            }
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

    function clearOptionGroupError(groupEl) {
        if (!groupEl) return;
        groupEl.classList.remove('is-invalid');
        var err = groupEl.querySelector('.option-group-error');
        if (err) {
            err.textContent = '';
            err.classList.add('hidden');
        }
    }

    function showOptionGroupError(groupEl) {
        var label = groupEl.getAttribute('data-group-label') || 'this option';
        groupEl.classList.add('is-invalid');
        var err = groupEl.querySelector('.option-group-error');
        if (err) {
            err.textContent = 'Please select an option for "' + label + '".';
            err.classList.remove('hidden');
        }
    }

    function groupHasSelection(groupEl) {
        var gname = groupEl.getAttribute('data-group-name');
        if (groupEl.querySelector('input[type="radio"][name="' + gname + '"]')) {
            return !!groupEl.querySelector('input[type="radio"][name="' + gname + '"]:checked');
        }
        if (groupEl.querySelector('input[type="checkbox"][name="' + gname + '[]"]')) {
            return !!groupEl.querySelector('input[type="checkbox"][name="' + gname + '[]"]:checked');
        }
        return false;
    }

    function clearAllOptionErrors() {
        document.querySelectorAll('.option-group-card').forEach(clearOptionGroupError);
        var formErr = document.getElementById('addToCartFormError');
        if (formErr) {
            formErr.textContent = '';
            formErr.classList.add('hidden');
        }
    }

    function validateDetailOptions() {
        var form = document.getElementById('productDetailForm');
        if (!form) return true;

        form.querySelectorAll('.injected-opt').forEach(function (el) { el.remove(); });
        clearAllOptionErrors();

        var firstInvalid = null;
        var invalidCount = 0;

        document.querySelectorAll('[data-group-name][data-required="1"]').forEach(function (groupEl) {
            if (!groupHasSelection(groupEl)) {
                invalidCount += 1;
                if (!firstInvalid) firstInvalid = groupEl;
                showOptionGroupError(groupEl);
            }
        });

        if (invalidCount > 0) {
            var formErr = document.getElementById('addToCartFormError');
            if (formErr) {
                formErr.textContent = invalidCount === 1
                    ? 'Please complete the required option above before adding to cart.'
                    : 'Please complete all required options above before adding to cart.';
                formErr.classList.remove('hidden');
            }
            if (firstInvalid) {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
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

    (function () {
        var form = document.getElementById('productDetailForm');
        if (!form) return;
        form.querySelectorAll('.option-group-card input[type="radio"], .option-group-card input[type="checkbox"]').forEach(function (input) {
            input.addEventListener('change', function () {
                var card = input.closest('.option-group-card');
                if (card && groupHasSelection(card)) {
                    clearOptionGroupError(card);
                    var remaining = document.querySelectorAll('.option-group-card.is-invalid').length;
                    if (remaining === 0) {
                        var formErr = document.getElementById('addToCartFormError');
                        if (formErr) {
                            formErr.textContent = '';
                            formErr.classList.add('hidden');
                        }
                    }
                }
            });
        });
    })();
    </script>
    @endpush
</x-client-layout>
