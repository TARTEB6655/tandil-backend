<?php

namespace App\Http\Controllers\Shop;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ShopAppliedCheckoutCoupon;
use App\Models\ShopMobileCheckout;
use App\Models\Setting;
use App\Models\User;
use App\Services\CategoryShippingService;
use App\Services\CategoryTaxService;
use App\Services\ShopCouponService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CartController extends Controller
{
    public const CURRENCY = 'AED';

    /**
     * Effective shipping amount (admin API or config). 0 = free.
     */
    public static function getEffectiveShippingAmount(): float
    {
        $v = Setting::get('shop_shipping_amount');

        return (float) ($v !== null && $v !== '' ? $v : config('shop.shipping_amount', 0));
    }

    /**
     * Effective tax percentage (admin API or config). e.g. 5 = 5%.
     */
    public static function getEffectiveTaxPercent(): float
    {
        $v = Setting::get('shop_tax_percent');

        return (float) ($v !== null && $v !== '' ? $v : config('shop.tax_percent', 5));
    }

    /**
     * Build order summary: 1-Subtotal, 2-Shipping (free or amount), 3-Tax (%), 4-Total.
     * Tax-exclusive pricing: product prices are without tax; tax is added at checkout.
     * Tax = Subtotal × (tax_percent / 100). Total = Subtotal - Discount + Shipping + Tax.
     * Same result as per-product tax when all products have the same tax rate.
     */
    /**
     * @param  iterable<int, Cart>|null  $cartItems  When provided, shipping is summed from per-category rates.
     */
    public static function buildOrderSummary(float $subtotal, float $discount = 0, ?iterable $cartItems = null): array
    {
        $shippingAmount = $cartItems !== null
            ? CategoryShippingService::resolveForCartItems($cartItems)['amount']
            : self::getEffectiveShippingAmount();

        $summary = self::buildOrderSummaryWithAdjustments($subtotal, $discount, 0, $shippingAmount);
        $summary = self::applyCategoryTaxToSummary($summary, $cartItems);

        return self::mergeCategoryShippingIntoSummary($summary, $cartItems);
    }

    /**
     * @param  iterable<int, Cart>|null  $cartItems
     */
    public static function mergeCategoryShippingIntoSummary(array $summary, ?iterable $cartItems = null): array
    {
        if ($cartItems === null) {
            return $summary;
        }

        $pack = CategoryShippingService::resolveForCartItems($cartItems);
        if ($pack['breakdown'] !== []) {
            $summary['category_shipping_breakdown'] = $pack['breakdown'];
            $summary['uses_category_shipping'] = $pack['uses_category_rates'];
        }

        return $summary;
    }

    /**
     * @param  iterable<int, Cart>|null  $cartItems
     */
    public static function resolveBaseShippingForCart(?iterable $cartItems): float
    {
        if ($cartItems === null) {
            return self::getEffectiveShippingAmount();
        }

        return CategoryShippingService::resolveForCartItems($cartItems)['amount'];
    }

    /**
     * @param  float  $merchandiseDiscount  Subtotal reduction (percentage / fixed coupons).
     * @param  float  $shippingDiscount  Amount waived from base shipping (e.g. free shipping coupon).
     * @param  float|null  $baseShipping  Defaults to shop setting when null.
     * @return array<string, mixed>
     */
    public static function buildOrderSummaryWithAdjustments(
        float $subtotal,
        float $merchandiseDiscount = 0,
        float $shippingDiscount = 0,
        ?float $baseShipping = null
    ): array {
        return self::buildOrderSummaryWithCoupon($subtotal, 0, $merchandiseDiscount, $shippingDiscount > 0, null, $shippingDiscount, $baseShipping);
    }

    /**
     * Order summary per coupon API contract: catalog discount separate from coupon; tax on amount after both.
     *
     * @return array<string, mixed>
     */
    public static function buildOrderSummaryWithCoupon(
        float $subtotal,
        float $catalogDiscount,
        float $couponDiscount,
        bool $freeShipping = false,
        ?string $couponCode = null,
        ?float $shippingDiscountOverride = null,
        ?float $baseShipping = null
    ): array {
        $baseShipping ??= self::getEffectiveShippingAmount();
        $taxPercent = self::getEffectiveTaxPercent();
        $catalogDiscount = round(max(0, $catalogDiscount), 2);
        $subtotal = round(max(0, $subtotal), 2);
        $afterCatalog = round(max(0, $subtotal - $catalogDiscount), 2);
        $couponDiscount = round(max(0, min($couponDiscount, $afterCatalog)), 2);

        $shippingDiscount = $shippingDiscountOverride !== null
            ? round(max(0, min($shippingDiscountOverride, $baseShipping)), 2)
            : ($freeShipping ? round($baseShipping, 2) : 0.0);
        $shippingAfter = round(max(0, $baseShipping - $shippingDiscount), 2);

        $taxable = round(max(0, $afterCatalog - $couponDiscount), 2);
        $taxAmount = round($taxable * ($taxPercent / 100), 2);
        $total = round($taxable + $taxAmount + $shippingAfter, 2);

        $summary = [
            'subtotal' => $subtotal,
            'discount' => $catalogDiscount,
            'coupon_discount' => $couponDiscount,
            'shipping_discount' => $shippingDiscount,
            'shipping' => $shippingAfter,
            'shipping_label' => $shippingAfter == 0 ? 'Free' : (string) $shippingAfter,
            'tax_percent' => $taxPercent,
            'tax' => $taxAmount,
            'total' => $total,
            'currency' => self::CURRENCY,
        ];

        if ($couponCode !== null && $couponCode !== '') {
            $summary['coupon_code'] = strtoupper($couponCode);
        }

        return $summary;
    }

    /**
     * @param  iterable<int, Cart>|null  $cartItems
     */
    public static function buildOrderSummaryWithCouponForCart(
        float $subtotal,
        float $catalogDiscount,
        float $couponDiscount,
        bool $freeShipping = false,
        ?string $couponCode = null,
        ?float $shippingDiscountOverride = null,
        ?iterable $cartItems = null
    ): array {
        $baseShipping = self::resolveBaseShippingForCart($cartItems);
        $summary = self::buildOrderSummaryWithCoupon(
            $subtotal,
            $catalogDiscount,
            $couponDiscount,
            $freeShipping,
            $couponCode,
            $shippingDiscountOverride,
            $baseShipping
        );

        $summary = self::applyCategoryTaxToSummary($summary, $cartItems);

        return self::mergeCategoryShippingIntoSummary($summary, $cartItems);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  iterable<int, Cart>|null  $cartItems
     * @return array<string, mixed>
     */
    public static function applyCategoryTaxToSummary(array $summary, ?iterable $cartItems = null): array
    {
        if ($cartItems === null) {
            return $summary;
        }

        $taxable = round(max(0, (float) $summary['subtotal'] - (float) ($summary['discount'] ?? 0) - (float) ($summary['coupon_discount'] ?? 0)), 2);
        $taxPack = CategoryTaxService::resolveForCartItems($cartItems, $taxable);
        $summary['tax'] = $taxPack['amount'];
        $summary['tax_percent'] = $taxPack['effective_percent'];
        $summary['category_tax_breakdown'] = $taxPack['breakdown'];
        $summary['uses_category_tax'] = $taxPack['uses_category_tax'];
        $summary['total'] = round($taxable + $taxPack['amount'] + (float) $summary['shipping'], 2);

        return $summary;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cart>  $items
     * @return array{
     *   catalog_discount: float,
     *   cart_category_ids: array<int>,
     *   cart_service_ids: array<int>,
     *   cart_catalog: string
     * }
     */
    public static function cartContextFromItems($items): array
    {
        $catalogDiscount = 0.0;
        $categoryIds = [];
        $serviceIds = [];
        $hasProduct = false;
        $hasServiceLine = false;

        foreach ($items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }
            $price = $item->lineUnitPrice();
            $compareAt = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
            if ($compareAt !== null && $compareAt > $price) {
                $catalogDiscount += ($compareAt - $price) * (int) $item->quantity;
            }
            if ($product->category_id) {
                $categoryIds[] = (int) $product->category_id;
            }
            if ($product->relationLoaded('services')) {
                foreach ($product->services as $service) {
                    $serviceIds[] = (int) $service->id;
                }
            }
            $type = strtolower((string) ($product->type ?? 'physical'));
            if ($type === 'service') {
                $hasServiceLine = true;
            } else {
                $hasProduct = true;
            }
        }

        $cartCatalog = Coupon::SCOPE_BOTH;
        if ($hasProduct && ! $hasServiceLine) {
            $cartCatalog = Coupon::SCOPE_PRODUCTS;
        } elseif ($hasServiceLine && ! $hasProduct) {
            $cartCatalog = Coupon::SCOPE_SERVICES;
        }

        return [
            'catalog_discount' => round($catalogDiscount, 2),
            'cart_category_ids' => array_values(array_unique($categoryIds)),
            'cart_service_ids' => array_values(array_unique($serviceIds)),
            'cart_catalog' => $cartCatalog,
        ];
    }

    /**
     * Preview wallet application against a gross order total (same rules as checkout: cap by balance and total,
     * optional AED minimum card remainder when paying by card).
     *
     * @return array{wallet_applied: float, amount_due: float}
     */
    public static function previewWalletAgainstOrder(
        float $orderTotalGross,
        float $userBalance,
        bool $useWallet,
        ?float $requestedWalletAmount
    ): array {
        $orderTotalGross = round($orderTotalGross, 2);
        $userBalance = round($userBalance, 2);

        if (! $useWallet || $userBalance <= 0 || $orderTotalGross <= 0) {
            return ['wallet_applied' => 0.0, 'amount_due' => $orderTotalGross];
        }

        $requested = $requestedWalletAmount !== null && $requestedWalletAmount > 0
            ? round(max(0, $requestedWalletAmount), 2)
            : $userBalance;

        $walletApplied = round(min($requested, $userBalance, $orderTotalGross), 2);
        $amountToCharge = max(0, round($orderTotalGross - $walletApplied, 2));

        $currency = strtolower((string) config('shop.currency', self::CURRENCY));
        $minAedCard = 2.0;
        if ($currency === 'aed' && $walletApplied > 0 && $amountToCharge > 0 && $amountToCharge < $minAedCard) {
            $shortfall = round($minAedCard - $amountToCharge, 2);
            $walletApplied = max(0, round($walletApplied - $shortfall, 2));
            $amountToCharge = max(0, round($orderTotalGross - $walletApplied, 2));
        }

        return ['wallet_applied' => $walletApplied, 'amount_due' => $amountToCharge];
    }

    /**
     * @param  array<string, mixed>  $orderSummary
     * @return array<string, mixed>
     */
    public static function mergeWalletPreviewIntoOrderSummary(array $orderSummary, Request $request, User $user): array
    {
        $balance = round((float) ($user->wallet_balance ?? 0), 2);

        if ($balance <= 0) {
            $orderSummary['wallet_available'] = false;

            return $orderSummary;
        }

        $orderSummary['wallet_available'] = true;
        $useWallet = $request->boolean('use_wallet');
        $requestedWallet = $request->filled('wallet_amount') ? (float) $request->input('wallet_amount') : null;
        $walletPreview = self::previewWalletAgainstOrder(
            (float) $orderSummary['total'],
            $balance,
            $useWallet,
            $requestedWallet
        );

        $orderSummary['wallet_balance'] = $balance;
        $orderSummary['use_wallet'] = $useWallet;
        $orderSummary['wallet_amount_applied'] = (float) $walletPreview['wallet_applied'];
        $orderSummary['amount_due'] = (float) $walletPreview['amount_due'];

        return $orderSummary;
    }

    /**
     * Format a cart item for frontend (Product Details / Shopping Cart / Review screens).
     */
    public static function cartItemToFrontend(Cart $item): array
    {
        $product = $item->product;
        $price = $item->lineUnitPrice();
        $compareAt = $product->compare_at_price !== null ? (float) $product->compare_at_price : null;
        $lineTotal = round($item->quantity * $price, 2);
        $selectedOptionIds = Cart::normalizeSelectedOptionIds($item->selected_options);
        $basePrice = round((float) $product->price, 2);
        $optionsDetail = Cart::resolveSelectedOptionsDisplay($product, $selectedOptionIds);
        $optionLabels = array_map(fn (array $row) => $row['label'], $optionsDetail);

        return [
            'id' => $item->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'image_url' => $product->image_url,
            'category' => $product->relationLoaded('category') && $product->category
                ? $product->category->name
                : null,
            'brand' => $product->vendor ?? null,
            'base_price' => $basePrice,
            'options_extra' => max(0, round($price - $basePrice, 2)),
            'current_price' => $price,
            'original_price' => $compareAt,
            'quantity' => $item->quantity,
            'line_total' => $lineTotal,
            'selected_option_ids' => $selectedOptionIds,
            'selected_options' => $optionLabels,
            'selected_options_detail' => $optionsDetail,
            'currency' => self::CURRENCY,
        ];
    }

    /**
     * Validation rules for variable product option IDs (accepts mobile + legacy keys).
     *
     * @return array<string, string>
     */
    public static function optionIdsValidationRules(): array
    {
        return [
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer|exists:product_options,id',
            'selected_option_ids' => 'nullable|array',
            'selected_option_ids.*' => 'integer|exists:product_options,id',
        ];
    }

    /**
     * @return array<int>
     */
    public static function selectedOptionIdsFromRequest(Request $request): array
    {
        foreach (['option_ids', 'optionIds', 'selected_option_ids', 'selectedOptionIds'] as $key) {
            if ($request->has($key)) {
                $raw = $request->input($key);
                $ids = Cart::normalizeSelectedOptionIds(is_array($raw) ? $raw : []);
                if ($ids !== []) {
                    return $ids;
                }
            }
        }

        $fromWebForm = [];
        foreach ($request->all() as $key => $value) {
            if (! is_string($key) || ! preg_match('/^opt_\d+$/', $key)) {
                continue;
            }
            if (is_array($value)) {
                foreach ($value as $v) {
                    $fromWebForm[] = (int) $v;
                }
            } elseif ($value !== null && $value !== '') {
                $fromWebForm[] = (int) $value;
            }
        }

        if ($fromWebForm !== []) {
            return Cart::normalizeSelectedOptionIds($fromWebForm);
        }

        return [];
    }

    /**
     * Resolve Buy Now quantity from request (quantity preferred, then qty, default 1).
     */
    private static function resolveBuyNowQuantity(Request $request): int
    {
        if ($request->filled('quantity')) {
            return max(1, (int) $request->input('quantity'));
        }
        if ($request->filled('qty')) {
            return max(1, (int) $request->input('qty'));
        }

        return 1;
    }

    /**
     * Add item to cart (Product Details → Add to Cart).
     */
    public function add(Request $request)
    {
        $request->validate(array_merge([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ], self::optionIdsValidationRules()));

        $user = $request->user();
        $product = Product::with('optionGroups.options')->findOrFail($request->product_id);

        $selectedOptionsNormalized = self::selectedOptionIdsFromRequest($request);
        $optionError = Cart::validateSelectedOptionsMessage($product, $selectedOptionsNormalized);
        if ($optionError !== null) {
            return ApiResponse::error($optionError, 422);
        }

        $unitPrice = Cart::calculateUnitPrice($product, $selectedOptionsNormalized);

        $cartItem = Cart::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->get()
            ->first(function (Cart $row) use ($selectedOptionsNormalized) {
                $current = Cart::normalizeSelectedOptionIds($row->selected_options);

                return $current === $selectedOptionsNormalized;
            });

        if ($cartItem) {
            $cartItem->quantity += (int) $request->quantity;
            $cartItem->unit_price = $unitPrice;
            $cartItem->selected_options = $selectedOptionsNormalized;
            $cartItem->save();
        } else {
            $cartItem = Cart::create([
                'user_id' => $user->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'selected_options' => $selectedOptionsNormalized,
                'unit_price' => $unitPrice,
            ]);
        }

        $cartItem->load(['product.category', 'product.primaryImage', 'product.optionGroups.options']);
        $data = self::cartItemToFrontend($cartItem);

        return ApiResponse::success('Item added to cart.', $data, 201);
    }

    /**
     * Cart lines + subtotal for checkout preview. Default = DB cart.
     * Optional query product_id + quantity = Buy Now without persisting cart (same idea as POST /api/shop/checkout/start with items).
     *
     * @return array{items: \Illuminate\Support\Collection<int, Cart>, subtotal: float}
     */
    public static function checkoutPreview(Request $request, int $userId): array
    {
        if ($request->filled('product_id')) {
            $request->validate(array_merge([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'sometimes|integer|min:1',
                'qty' => 'sometimes|integer|min:1',
            ], self::optionIdsValidationRules()));
            $qty = self::resolveBuyNowQuantity($request);
            $product = Product::with(['category', 'primaryImage', 'services', 'optionGroups.options'])
                ->findOrFail((int) $request->input('product_id'));
            $selectedOptionsNormalized = self::selectedOptionIdsFromRequest($request);
            $unitPrice = Cart::calculateUnitPrice($product, $selectedOptionsNormalized);
            $cart = new Cart([
                'user_id' => $userId,
                'product_id' => $product->id,
                'quantity' => $qty,
                'selected_options' => $selectedOptionsNormalized,
                'unit_price' => $unitPrice,
            ]);
            $cart->setRelation('product', $product);
            $cart->id = 0;
            $subtotal = round($qty * $unitPrice, 2);

            $items = collect([$cart]);
            $ctx = self::cartContextFromItems($items);

            return [
                'items' => $items,
                'subtotal' => $subtotal,
                'catalog_discount' => $ctx['catalog_discount'],
                'cart_category_ids' => $ctx['cart_category_ids'],
                'cart_service_ids' => $ctx['cart_service_ids'],
                'cart_catalog' => $ctx['cart_catalog'],
            ];
        }

        $cartItems = Cart::where('user_id', $userId)
            ->with(['product.category', 'product.primaryImage', 'product.services'])
            ->get();
        $validItems = $cartItems->filter(fn ($item) => $item->product !== null)->values();
        $subtotal = round($validItems->sum(fn ($item) => $item->quantity * $item->lineUnitPrice()), 2);
        $ctx = self::cartContextFromItems($validItems);

        return [
            'items' => $validItems,
            'subtotal' => $subtotal,
            'catalog_discount' => $ctx['catalog_discount'],
            'cart_category_ids' => $ctx['cart_category_ids'],
            'cart_service_ids' => $ctx['cart_service_ids'],
            'cart_catalog' => $ctx['cart_catalog'],
        ];
    }

    /**
     * GET /api/shop/order-summary
     * Returns order summary for checkout (Address/Payment/Review).
     * Tax-exclusive: subtotal = sum of item prices; tax = subtotal × (tax_percent/100); total = subtotal - discount + shipping + tax.
     * Uses current user's cart unless query product_id (+ optional quantity) is sent for Buy Now preview (cart can be empty).
     * Shipping and tax % from shop settings (settings table).
     *
     * Optional query (same behaviour as POST buy-now/summary): `use_wallet` (boolean), `wallet_amount` (number, caps applied amount when use_wallet is true).
     */
    public function orderSummary(Request $request)
    {
        $request->validate([
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'sometimes|numeric|min:0',
            'coupon_code' => 'sometimes|string|max:64',
        ]);

        $user = $request->user();
        $pack = self::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return ApiResponse::error($pack['error'], 422);
        }

        $orderSummary = $pack['order_summary'];
        $orderSummary = self::mergeWalletPreviewIntoOrderSummary($orderSummary, $request, $user);

        return ApiResponse::success('Order summary retrieved.', $orderSummary);
    }

    /**
     * POST /api/shop/buy-now/summary
     * Wallet-focused summary endpoint using current user's cart.
     */
    public function buyNowSummary(Request $request)
    {
        $request->validate(array_merge([
            'product_id' => 'sometimes|exists:products,id',
            'quantity' => 'sometimes|integer|min:1',
            'qty' => 'sometimes|integer|min:1',
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'sometimes|numeric|min:0',
            'coupon_code' => 'sometimes|string|max:64',
        ], self::optionIdsValidationRules()));

        $user = $request->user();
        $pack = self::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return ApiResponse::error($pack['error'], 422);
        }

        $orderSummary = $pack['order_summary'];
        $orderSummary = self::mergeWalletPreviewIntoOrderSummary($orderSummary, $request, $user);

        return ApiResponse::success('Buy now summary retrieved.', [
            'order_summary' => $orderSummary,
        ]);
    }

    /**
     * Checkout amounts for coupons: use the server cart when the user has cart lines so
     * min-order checks match GET /cart and order-summary.
     *
     * @param  array<string, mixed>  $cartPreview
     * @return array{
     *   subtotal: float,
     *   catalog_discount: float,
     *   cart_category_ids: array<int>,
     *   cart_service_ids: array<int>,
     *   cart_catalog: string
     * }
     */
    public static function resolveCheckoutAmountsFromRequest(Request $request, array $cartPreview): array
    {
        $items = $cartPreview['items'] ?? collect();
        $hasServerCart = $items instanceof \Illuminate\Support\Collection
            ? $items->isNotEmpty()
            : (is_countable($items) && count($items) > 0);

        if ($hasServerCart && ! $request->filled('product_id')) {
            return [
                'subtotal' => round((float) ($cartPreview['subtotal'] ?? 0), 2),
                'catalog_discount' => round((float) ($cartPreview['catalog_discount'] ?? 0), 2),
                'cart_category_ids' => array_map('intval', (array) ($cartPreview['cart_category_ids'] ?? [])),
                'cart_service_ids' => array_map('intval', (array) ($cartPreview['cart_service_ids'] ?? [])),
                'cart_catalog' => (string) ($cartPreview['cart_catalog'] ?? Coupon::SCOPE_BOTH),
            ];
        }

        $subtotal = $request->filled('subtotal')
            ? round((float) $request->input('subtotal'), 2)
            : round((float) ($cartPreview['subtotal'] ?? 0), 2);
        $catalogDiscount = $request->filled('catalog_discount')
            ? round((float) $request->input('catalog_discount'), 2)
            : round((float) ($cartPreview['catalog_discount'] ?? 0), 2);

        $cartCategoryIds = $request->input('cart_category_ids', $cartPreview['cart_category_ids'] ?? []);
        if (is_string($cartCategoryIds)) {
            $decoded = json_decode($cartCategoryIds, true);
            $cartCategoryIds = is_array($decoded) ? $decoded : [];
        }
        $cartServiceIds = $request->input('cart_service_ids', $cartPreview['cart_service_ids'] ?? []);
        if (is_string($cartServiceIds)) {
            $decoded = json_decode($cartServiceIds, true);
            $cartServiceIds = is_array($decoded) ? $decoded : [];
        }

        return [
            'subtotal' => $subtotal,
            'catalog_discount' => $catalogDiscount,
            'cart_category_ids' => array_map('intval', (array) $cartCategoryIds),
            'cart_service_ids' => array_map('intval', (array) $cartServiceIds),
            'cart_catalog' => (string) $request->input('cart_catalog', $cartPreview['cart_catalog'] ?? Coupon::SCOPE_BOTH),
        ];
    }

    /**
     * Cart / order-summary / checkout totals with optional coupon_code (query or JSON body).
     *
     * @return array{
     *   cart_preview: array{items: \Illuminate\Support\Collection, subtotal: float},
     *   order_summary: array<string, mixed>,
     *   coupon_id: ?int,
     *   coupon_code: ?string,
     *   coupon_merchandise_discount: float,
     *   coupon_shipping_discount: float,
     *   error: ?string,
     *   error_details: array<string, mixed>
     * }
     */
    /**
     * Mobile apps often send `code` (same as coupons/apply) while order-summary may use coupon_code.
     */
    public static function normalizeCheckoutCouponInput(Request $request): void
    {
        if ($request->filled('coupon_code')) {
            return;
        }

        foreach (['code', 'promo_code', 'promoCode', 'couponCode'] as $key) {
            $raw = $request->input($key, $request->query($key));
            if (is_string($raw) && trim($raw) !== '') {
                $request->merge(['coupon_code' => trim($raw)]);

                return;
            }
        }
    }

    public static function checkoutTotalsForRequest(Request $request, User $user, bool $strictCoupon = false): array
    {
        self::normalizeCheckoutCouponInput($request);

        $cartPreview = self::checkoutPreview($request, $user->id);

        foreach ($cartPreview['items'] as $cart) {
            if ($cart->product === null) {
                continue;
            }
            $optionError = Cart::validateSelectedOptionsMessage($cart->product, $cart->selected_options ?? []);
            if ($optionError !== null) {
                return [
                    'cart_preview' => $cartPreview,
                    'order_summary' => [],
                    'coupon_id' => null,
                    'coupon_code' => null,
                    'coupon_merchandise_discount' => 0.0,
                    'coupon_shipping_discount' => 0.0,
                    'error' => $optionError,
                    'error_details' => [],
                ];
            }
        }

        $amounts = self::resolveCheckoutAmountsFromRequest($request, $cartPreview);
        $subtotal = $amounts['subtotal'];
        $catalogDiscount = $amounts['catalog_discount'];
        $cartCategoryIds = $amounts['cart_category_ids'];
        $cartServiceIds = $amounts['cart_service_ids'];
        $cartCatalog = $amounts['cart_catalog'];
        $code = self::resolveCheckoutCouponCode($request, $user, $cartPreview);
        if ($code !== '') {
            $request->merge(['coupon_code' => $code]);
        }

        $cartItems = $cartPreview['items'];

        if ($code === '') {
            $summary = self::buildOrderSummaryWithCouponForCart($subtotal, $catalogDiscount, 0, false, null, null, $cartItems);
            self::normalizeOrderSummaryNumericTypes($summary);

            return [
                'cart_preview' => $cartPreview,
                'order_summary' => $summary,
                'coupon_id' => null,
                'coupon_code' => null,
                'coupon_merchandise_discount' => 0.0,
                'coupon_shipping_discount' => 0.0,
                'error' => null,
                'error_details' => [],
            ];
        }

        if ($subtotal < 0.01 && ! $request->filled('product_id')) {
            return [
                'cart_preview' => $cartPreview,
                'order_summary' => [],
                'coupon_id' => null,
                'coupon_code' => null,
                'coupon_merchandise_discount' => 0.0,
                'coupon_shipping_discount' => 0.0,
                'error' => 'Your cart is empty. Add items before applying a coupon.',
                'error_details' => ['subtotal' => 0.0],
            ];
        }

        $pack = self::checkoutPackWithOptionalCoupon(
            $cartPreview,
            $subtotal,
            $catalogDiscount,
            $cartCategoryIds,
            $cartCatalog,
            $cartServiceIds,
            $code,
            (int) $user->id,
            $cartItems,
            $strictCoupon
        );
        if ($pack['error'] === null) {
            self::rememberAppliedCheckoutCouponFromPack($request, $user, $cartPreview, $pack);
        }

        return $pack;
    }

    /**
     * Fingerprint of cart lines + subtotal so a stored coupon applies only to the same checkout basket.
     *
     * @param  array<string, mixed>  $cartPreview
     */
    public static function checkoutCartFingerprint(Request $request, array $cartPreview): string
    {
        $lines = [];
        foreach ($cartPreview['items'] ?? [] as $cart) {
            if ($cart->product === null) {
                continue;
            }
            $lines[] = $cart->checkoutLinePayload();
        }
        usort($lines, function (array $a, array $b): int {
            $cmp = $a['product_id'] <=> $b['product_id'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return json_encode($a['selected_options'] ?? []) <=> json_encode($b['selected_options'] ?? []);
        });

        $payload = [
            'lines' => $lines,
            'subtotal' => round((float) ($cartPreview['subtotal'] ?? 0), 2),
            'catalog_discount' => round((float) ($cartPreview['catalog_discount'] ?? 0), 2),
        ];
        if ($request->filled('product_id')) {
            $payload['buy_now_product_id'] = (int) $request->input('product_id');
            $payload['buy_now_quantity'] = self::resolveBuyNowQuantity($request);
            $payload['buy_now_option_ids'] = self::selectedOptionIdsFromRequest($request);
        }

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public static function storeAppliedCheckoutCoupon(
        int $userId,
        string $code,
        Request $request,
        array $cartPreview,
        ?int $couponId = null
    ): void {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return;
        }

        $fingerprint = self::checkoutCartFingerprint($request, $cartPreview);

        ShopAppliedCheckoutCoupon::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'cart_fingerprint' => $fingerprint,
                'coupon_id' => $couponId,
                'coupon_code' => $code,
            ]
        );

        Cache::put(self::checkoutCouponCacheKey($userId), [
            'code' => $code,
            'fingerprint' => $fingerprint,
            'coupon_id' => $couponId,
        ], now()->addHours(4));
    }

    public static function clearAppliedCheckoutCoupon(int $userId): void
    {
        ShopAppliedCheckoutCoupon::query()->where('user_id', $userId)->delete();
        Cache::forget(self::checkoutCouponCacheKey($userId));
        ShopMobileCheckout::query()
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->update([
                'coupon_id' => null,
                'coupon_code' => null,
                'coupon_merchandise_discount' => 0,
                'coupon_shipping_discount' => 0,
            ]);
    }

    /**
     * Resolve coupon for checkout totals.
     *
     * When the client sends coupon_code explicitly, that value wins (empty clears storage).
     * When omitted on a plain summary refresh, no coupon is applied (full total).
     * When omitted during a wallet toggle refresh (use_wallet / wallet_amount sent), restore
     * the last applied coupon for the same cart so wallet + coupon totals stay correct.
     *
     * @param  array<string, mixed>  $cartPreview
     */
    public static function resolveCheckoutCouponCode(Request $request, User $user, array $cartPreview): string
    {
        if ($request->boolean('clear_coupon')) {
            self::clearAppliedCheckoutCoupon((int) $user->id);

            return '';
        }

        $explicit = $request->has('coupon_code') || $request->query->has('coupon_code');
        if ($explicit) {
            $rawCoupon = $request->input('coupon_code', $request->query('coupon_code'));
            $code = is_string($rawCoupon) ? trim($rawCoupon) : '';

            if ($code === '') {
                self::clearAppliedCheckoutCoupon((int) $user->id);

                return '';
            }

            return strtoupper($code);
        }

        if (! self::shouldRestoreStoredCoupon($request)) {
            return '';
        }

        $stored = self::storedCheckoutCouponForCurrentCart((int) $user->id, $request, $cartPreview);

        return $stored ?? '';
    }

    /**
     * Wallet-toggle refreshes omit coupon_code; other refreshes should not silently re-apply a coupon.
     */
    public static function shouldRestoreStoredCoupon(Request $request): bool
    {
        if ($request->has('use_wallet') || $request->query->has('use_wallet')) {
            return true;
        }

        if ($request->filled('wallet_amount') || $request->query->has('wallet_amount')) {
            return true;
        }

        return false;
    }

    /**
     * Last coupon the user applied for this exact cart (wallet toggle / checkout refresh).
     *
     * @param  array<string, mixed>  $cartPreview
     */
    public static function storedCheckoutCouponForCurrentCart(int $userId, Request $request, array $cartPreview): ?string
    {
        $fingerprint = self::checkoutCartFingerprint($request, $cartPreview);

        $cached = Cache::get(self::checkoutCouponCacheKey($userId));
        if (is_array($cached) && ($cached['fingerprint'] ?? '') === $fingerprint) {
            $code = trim((string) ($cached['code'] ?? ''));
            if ($code !== '') {
                return strtoupper($code);
            }
        }

        $row = ShopAppliedCheckoutCoupon::query()->where('user_id', $userId)->first();
        if ($row !== null && (string) $row->cart_fingerprint === $fingerprint) {
            $code = trim((string) $row->coupon_code);
            if ($code !== '') {
                return strtoupper($code);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cartPreview
     * @param  array<string, mixed>  $pack
     */
    public static function rememberAppliedCheckoutCouponFromPack(Request $request, User $user, array $cartPreview, array $pack): void
    {
        $code = $pack['coupon_code'] ?? null;
        if (! is_string($code) || trim($code) === '') {
            return;
        }

        self::storeAppliedCheckoutCoupon(
            (int) $user->id,
            $code,
            $request,
            $cartPreview,
            isset($pack['coupon_id']) ? (int) $pack['coupon_id'] : null
        );
    }

    private static function checkoutCouponCacheKey(int $userId): string
    {
        return 'shop_checkout_coupon:'.$userId;
    }

    /**
     * Checkout totals: coupon is optional — invalid/inactive codes are ignored (order still proceeds).
     * Use POST /api/shop/coupons/apply for strict validation when the user taps Apply.
     *
     * @param  array<int>  $cartCategoryIds
     * @param  array<int>  $cartServiceIds
     * @return array{
     *   cart_preview: array<string, mixed>,
     *   order_summary: array<string, mixed>,
     *   coupon_id: ?int,
     *   coupon_code: ?string,
     *   coupon_merchandise_discount: float,
     *   coupon_shipping_discount: float,
     *   error: ?string,
     *   error_details: array<string, mixed>
     * }
     */
    public static function checkoutPackWithOptionalCoupon(
        array $cartPreview,
        float $subtotal,
        float $catalogDiscount,
        array $cartCategoryIds,
        string $cartCatalog,
        array $cartServiceIds,
        string $code,
        int $userId,
        ?iterable $cartItems = null,
        bool $strictCoupon = false
    ): array {
        $baseShipping = self::resolveBaseShippingForCart($cartItems);

        /** @var ShopCouponService $svc */
        $svc = app(ShopCouponService::class);
        $r = $svc->preview($code, $subtotal, $catalogDiscount, $userId, $cartCategoryIds, $cartCatalog, $cartServiceIds, $baseShipping);

        if (! ($r['ok'] ?? false)) {
            if ($strictCoupon) {
                return [
                    'cart_preview' => $cartPreview,
                    'order_summary' => [],
                    'coupon_id' => null,
                    'coupon_code' => null,
                    'coupon_merchandise_discount' => 0.0,
                    'coupon_shipping_discount' => 0.0,
                    'error' => $r['message'] ?? 'Invalid coupon code.',
                    'error_details' => is_array($r['error_details'] ?? null) ? $r['error_details'] : [],
                ];
            }

            $summary = self::buildOrderSummaryWithCouponForCart($subtotal, $catalogDiscount, 0, false, null, null, $cartItems);
            self::normalizeOrderSummaryNumericTypes($summary);
            unset($summary['coupon_code']);

            return [
                'cart_preview' => $cartPreview,
                'order_summary' => $summary,
                'coupon_id' => null,
                'coupon_code' => null,
                'coupon_merchandise_discount' => 0.0,
                'coupon_shipping_discount' => 0.0,
                'error' => null,
                'error_details' => [],
            ];
        }

        $summary = self::mergeCategoryShippingIntoSummary($r['order_summary'], $cartItems);
        self::normalizeOrderSummaryNumericTypes($summary);
        $summary['coupon'] = $r['coupon'];

        $couponDiscount = (float) ($r['coupon_discount'] ?? 0);
        $freeShipping = (bool) ($r['free_shipping'] ?? false);
        $shipDisc = $freeShipping ? (float) ($summary['shipping_discount'] ?? 0) : 0.0;

        return [
            'cart_preview' => $cartPreview,
            'order_summary' => $summary,
            'coupon_id' => $r['coupon_id'] ?? null,
            'coupon_code' => $r['code'] ?? null,
            'coupon_merchandise_discount' => $couponDiscount,
            'coupon_shipping_discount' => $shipDisc,
            'error' => null,
            'error_details' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function normalizeOrderSummaryNumericTypes(array &$summary): void
    {
        $summary['subtotal'] = (float) $summary['subtotal'];
        $summary['discount'] = (float) ($summary['discount'] ?? 0);
        if (array_key_exists('coupon_discount', $summary)) {
            $summary['coupon_discount'] = (float) $summary['coupon_discount'];
        }
        if (array_key_exists('shipping_discount', $summary)) {
            $summary['shipping_discount'] = (float) $summary['shipping_discount'];
        }
        $summary['shipping'] = (float) $summary['shipping'];
        $summary['tax_percent'] = (float) $summary['tax_percent'];
        $summary['tax'] = (float) $summary['tax'];
        $summary['total'] = (float) $summary['total'];
    }

    /**
     * Mobile checkout UI labels (Payment summary: Subtotal, VAT, Total).
     *
     * @param  array<string, mixed>  $summary
     */
    public static function addCheckoutUiAliases(array &$summary): void
    {
        self::normalizeOrderSummaryNumericTypes($summary);
        $summary['vat'] = (float) ($summary['tax'] ?? 0);
        $summary['vat_percent'] = (float) ($summary['tax_percent'] ?? 0);
    }

    /**
     * View cart (Shopping Cart screen: items + order summary).
     */
    public function view(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'use_wallet' => 'sometimes|boolean',
            'wallet_amount' => 'sometimes|numeric|min:0',
            'coupon_code' => 'sometimes|string|max:64',
        ]);

        $cartItems = Cart::where('user_id', $user->id)
            ->with(['product.category', 'product.primaryImage', 'product.optionGroups.options'])
            ->get();

        $validItems = $cartItems->filter(fn ($item) => $item->product !== null);

        $items = $validItems->map(fn ($item) => self::cartItemToFrontend($item))->values()->all();

        $pack = self::checkoutTotalsForRequest($request, $user);
        if ($pack['error'] !== null) {
            return ApiResponse::error($pack['error'], 422);
        }

        $orderSummary = $pack['order_summary'];
        $orderSummary = self::mergeWalletPreviewIntoOrderSummary($orderSummary, $request, $user);

        return ApiResponse::success('Cart retrieved successfully.', [
            'items' => $items,
            'order_summary' => $orderSummary,
        ]);
    }

    /**
     * Update cart item quantity (Shopping Cart +/- controls).
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->quantity = $request->quantity;
        $cartItem->save();

        $cartItem->load(['product.category', 'product.primaryImage', 'product.optionGroups.options']);
        $data = self::cartItemToFrontend($cartItem);

        return ApiResponse::success('Cart item updated.', $data);
    }

    /**
     * Remove item from cart (Shopping Cart trash icon).
     */
    public function remove(Request $request, $id)
    {
        $user = $request->user();
        $cartItem = Cart::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $cartItem->delete();

        return ApiResponse::success('Item removed from cart.');
    }
}
