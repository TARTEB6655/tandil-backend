<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Area-based (per m²) pricing for service products only.
 * Global Admin Setting (like Instant Order Fee) — applies to ALL Services-channel products.
 * Category/shop simple products are unchanged (Instant Order Fee still applies there).
 */
final class ServiceAreaPricing
{
    private static ?bool $cartsAreaModeColumnReady = null;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PER_M2 = 'per_m2';

    public const SETTING_PRICING_TYPE = 'service_pricing_type';

    public const VENDOR_SETTING_SHOW_ON_CLIENT = 'service_pricing_show_on_client';

    public const CLIENT_VISIBILITY_TOGGLE_LABEL = 'Show on client products & services';

    public const CLIENT_VISIBILITY_TOGGLE_DESCRIPTION = 'When off, customers do not see this pricing on the shop app. You can still save prices for later.';

    public const SETTING_PRICE = 'service_pricing_price';

    public const VENDOR_ENABLE_FIXED = 'service_pricing_enable_fixed';

    public const VENDOR_ENABLE_PER_M2 = 'service_pricing_enable_per_m2';

    public const VENDOR_FIXED_AMOUNT = 'service_pricing_fixed_amount';

    public const VENDOR_PER_M2_RATE = 'service_pricing_per_m2_rate';

    public const SETTING_INCLUDES = 'service_pricing_includes';

    /** @var list<string> */
    public const INCLUDE_KEYS = [
        'materials',
        'installation',
        'labor',
        'transportation',
        'delivery',
    ];

    /** @var array<string, string> */
    public const INCLUDE_LABELS = [
        'materials' => 'Materials',
        'installation' => 'Installation',
        'labor' => 'Labor',
        'transportation' => 'Transportation',
        'delivery' => 'Delivery',
    ];

    /**
     * @return array{pricing_type: string, price: float, price_includes: array<string, bool>}
     */
    public static function cartsAreaModeColumnReady(): bool
    {
        if (self::$cartsAreaModeColumnReady === null) {
            try {
                self::$cartsAreaModeColumnReady = Schema::hasTable('carts')
                    && Schema::hasColumn('carts', 'area_pricing_mode');
            } catch (\Throwable) {
                self::$cartsAreaModeColumnReady = false;
            }
        }

        return self::$cartsAreaModeColumnReady;
    }

    public static function globalConfig(): array
    {
        $type = self::normalizeType(Setting::get(self::SETTING_PRICING_TYPE, self::TYPE_FIXED), true);
        $priceRaw = Setting::get(self::SETTING_PRICE, '0');
        $price = max(0, round((float) ($priceRaw === null || $priceRaw === '' ? 0 : $priceRaw), 2));
        $includesRaw = Setting::get(self::SETTING_INCLUDES, null);
        $includes = self::normalizeIncludes($includesRaw, true) ?? self::emptyIncludes();

        return [
            'pricing_type' => $type,
            'price' => $price,
            'price_includes' => $includes,
        ];
    }

    /**
     * Persist global settings.
     * Syncs pricing_type + price_includes to services/products — does NOT overwrite catalog `price`
     * (store listing must keep each product's own price; rate is applied at checkout).
     *
     * @param  array<string, bool>|null  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function saveGlobal(string $pricingType, float $price, ?array $includes): array
    {
        $type = self::normalizeType($pricingType, true);
        $price = max(0, round($price, 2));
        $includes = self::normalizeIncludes($includes, true) ?? self::emptyIncludes();

        Setting::set(self::SETTING_PRICING_TYPE, $type, 'text', 'services');
        Setting::set(self::SETTING_PRICE, (string) $price, 'text', 'services');
        Setting::set(self::SETTING_INCLUDES, json_encode($includes), 'json', 'services');

        return self::syncAllServicesAndProducts($type, $price, $includes);
    }

    /**
     * Save only Price includes (UI tree/palm screen) — does not touch fixed / per_m2 rate.
     *
     * @param  array<string, bool>|null  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function savePriceIncludesOnly(?array $includes): array
    {
        $config = self::globalConfig();
        $includes = self::normalizeIncludes($includes, true) ?? self::emptyIncludes();
        Setting::set(self::SETTING_INCLUDES, json_encode($includes), 'json', 'services');

        return self::syncAllServicesAndProducts($config['pricing_type'], $config['price'], $includes);
    }

    /**
     * Sync pricing_type + includes only — never overwrite product/service catalog price.
     *
     * @param  array<string, bool>  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function syncAllServicesAndProducts(string $pricingType, float $price, array $includes): array
    {
        $servicesUpdated = 0;
        Service::query()->orderBy('id')->chunkById(100, function ($services) use ($pricingType, $includes, &$servicesUpdated) {
            foreach ($services as $service) {
                $service->pricing_type = $pricingType;
                $service->price_includes = $includes;
                $service->save();
                $servicesUpdated++;
            }
        });

        $productsUpdated = 0;
        Product::query()->where('type', 'service')->orderBy('id')->chunkById(100, function ($products) use ($pricingType, $includes, &$productsUpdated) {
            foreach ($products as $product) {
                $product->pricing_type = $pricingType;
                $product->price_includes = $includes;
                // Keep $product->price (catalog / list price) unchanged.
                $product->save();
                $productsUpdated++;
            }
        });

        return [
            'synced_services' => $servicesUpdated,
            'synced_products' => $productsUpdated,
        ];
    }

    public static function vendorSettingKey(int $vendorId, string $suffix): string
    {
        return 'vendor_'.$vendorId.'_'.$suffix;
    }

    /**
     * @return array{pricing_type: string, price: float, price_includes: array<string, bool>}
     */
    public static function vendorConfig(int $vendorId): array
    {
        $expanded = self::vendorConfigExpanded($vendorId);

        return [
            'pricing_type' => $expanded['pricing_type'],
            'price' => $expanded['price'],
            'price_includes' => $expanded['price_includes'],
            'show_on_client_products_services' => $expanded['show_on_client_products_services'],
            'enable_fixed_price' => $expanded['enable_fixed_price'],
            'enable_per_m2' => $expanded['enable_per_m2'],
            'fixed_price' => $expanded['fixed_price'],
            'per_m2_price' => $expanded['per_m2_price'],
            'area_pricing_multiselect' => $expanded['area_pricing_multiselect'],
        ];
    }

    /**
     * Vendor area pricing — supports multiselect (fixed + per m²) with legacy type+price fallback.
     *
     * @return array{
     *     pricing_type: string,
     *     price: float,
     *     price_includes: array<string, bool>,
     *     show_on_client_products_services: bool,
     *     enable_fixed_price: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float,
     *     area_pricing_multiselect: bool
     * }
     */
    public static function vendorConfigExpanded(int $vendorId): array
    {
        $includesRaw = Setting::get(self::vendorSettingKey($vendorId, 'service_pricing_includes'), null);
        $includes = self::normalizeIncludes($includesRaw, true) ?? self::emptyIncludes();
        $show = self::vendorShowAreaPricingOnClient($vendorId);

        $hasMultiKeys = Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_FIXED)) !== null
            || Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_PER_M2)) !== null;

        if ($hasMultiKeys) {
            $enableFixed = filter_var(
                Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_FIXED), '0'),
                FILTER_VALIDATE_BOOLEAN
            );
            $enablePerM2 = filter_var(
                Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_PER_M2), '0'),
                FILTER_VALIDATE_BOOLEAN
            );
            $fixedPrice = max(0, round((float) Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_FIXED_AMOUNT), '0'), 2));
            $perM2Price = max(0, round((float) Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_PER_M2_RATE), '0'), 2));
        } else {
            $type = self::normalizeType(
                Setting::get(self::vendorSettingKey($vendorId, 'service_pricing_type'), self::TYPE_FIXED),
                true
            );
            $priceRaw = Setting::get(self::vendorSettingKey($vendorId, 'service_pricing_price'), '0');
            $legacyPrice = max(0, round((float) ($priceRaw === null || $priceRaw === '' ? 0 : $priceRaw), 2));
            $enableFixed = $type === self::TYPE_FIXED;
            $enablePerM2 = $type === self::TYPE_PER_M2;
            $fixedPrice = $enableFixed ? $legacyPrice : 0.0;
            $perM2Price = $enablePerM2 ? $legacyPrice : 0.0;
        }

        $multiselect = $enableFixed && $enablePerM2;
        $pricingType = $multiselect
            ? 'multi'
            : ($enablePerM2 ? self::TYPE_PER_M2 : self::TYPE_FIXED);
        $legacyPrice = $multiselect
            ? 0.0
            : ($enablePerM2 ? $perM2Price : $fixedPrice);

        return [
            'pricing_type' => $pricingType,
            'price' => $legacyPrice,
            'price_includes' => $includes,
            'show_on_client_products_services' => $show,
            'enable_fixed_price' => $enableFixed,
            'enable_per_m2' => $enablePerM2,
            'fixed_price' => $fixedPrice,
            'per_m2_price' => $perM2Price,
            'area_pricing_multiselect' => $multiselect,
        ];
    }

    /**
     * @return array{fixed: bool, per_m2: bool, fixed_amount: float, per_m2_rate: float}
     */
    public static function areaModesForProduct(Product $product): array
    {
        $vendorId = (int) ($product->vendor_id ?? 0);
        if ($vendorId > 0) {
            $config = self::vendorConfigExpanded($vendorId);

            return [
                'fixed' => (bool) $config['enable_fixed_price'],
                'per_m2' => (bool) $config['enable_per_m2'],
                'fixed_amount' => (float) $config['fixed_price'],
                'per_m2_rate' => (float) $config['per_m2_price'],
            ];
        }

        $config = self::catalogServiceConfig($product);
        $type = $config['pricing_type'];

        return [
            'fixed' => $type === self::TYPE_FIXED,
            'per_m2' => $type === self::TYPE_PER_M2,
            'fixed_amount' => $type === self::TYPE_FIXED ? (float) $config['price'] : 0.0,
            'per_m2_rate' => $type === self::TYPE_PER_M2 ? (float) $config['price'] : 0.0,
        ];
    }

    public static function requiresAreaPricingModeSelection(Product $product): bool
    {
        if (! self::appliesToProduct($product) || ! self::areaPricingVisibleOnClient($product)) {
            return false;
        }
        $modes = self::areaModesForProduct($product);

        return $modes['fixed'] && $modes['per_m2'];
    }

    public static function implicitAreaPricingMode(Product $product): string
    {
        $modes = self::areaModesForProduct($product);
        if ($modes['per_m2'] && ! $modes['fixed']) {
            return self::TYPE_PER_M2;
        }

        return self::TYPE_FIXED;
    }

    public static function normalizeAreaPricingMode(mixed $mode): ?string
    {
        if ($mode === null || $mode === '') {
            return null;
        }
        $mode = strtolower(trim((string) $mode));
        if (in_array($mode, [self::TYPE_FIXED, 'fixed_price', 'flat'], true)) {
            return self::TYPE_FIXED;
        }
        if (in_array($mode, [self::TYPE_PER_M2, 'per_sqm', 'sqm', 'm2', 'area', 'square_meter'], true)) {
            return self::TYPE_PER_M2;
        }

        return null;
    }

    public static function resolveAreaPricingModeFromRequest(\Illuminate\Http\Request $request): ?string
    {
        foreach ([
            'area_pricing_mode',
            'areaPricingMode',
            'pricing_mode',
            'pricingMode',
        ] as $key) {
            if ($request->has($key)) {
                return self::normalizeAreaPricingMode($request->input($key));
            }
        }

        return null;
    }

    public static function resolveAreaPricingModeFromArray(array $payload): ?string
    {
        foreach ([
            'area_pricing_mode',
            'areaPricingMode',
            'pricing_mode',
            'pricingMode',
        ] as $key) {
            if (array_key_exists($key, $payload)) {
                return self::normalizeAreaPricingMode($payload[$key]);
            }
        }

        return null;
    }

    public static function validateAreaPricingModeMessage(Product $product, ?string $mode): ?string
    {
        if (! self::appliesToProduct($product) || ! self::areaPricingVisibleOnClient($product)) {
            return null;
        }

        if (! self::requiresAreaPricingModeSelection($product)) {
            return null;
        }

        if ($mode === null) {
            return 'Select Fixed price or Price per square meter (m²) for this item.';
        }

        $modes = self::areaModesForProduct($product);
        if ($mode === self::TYPE_FIXED && ! $modes['fixed']) {
            return 'Fixed price is not available for this product.';
        }
        if ($mode === self::TYPE_PER_M2 && ! $modes['per_m2']) {
            return 'Price per m² is not available for this product.';
        }

        return null;
    }

    public static function resolveAreaPricingModeForCheckout(Product $product, ?string $requestedMode): string
    {
        $normalized = self::normalizeAreaPricingMode($requestedMode);
        $error = self::validateAreaPricingModeMessage($product, $normalized);
        if ($error !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'area_pricing_mode' => [$error],
            ]);
        }

        if ($normalized !== null) {
            return $normalized;
        }

        return self::implicitAreaPricingMode($product);
    }

    public static function vendorShowAreaPricingOnClient(int $vendorId): bool
    {
        $raw = Setting::get(self::vendorSettingKey($vendorId, self::VENDOR_SETTING_SHOW_ON_CLIENT), '1');

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function saveVendorAreaPricingVisibility(int $vendorId, bool $show): void
    {
        Setting::set(
            self::vendorSettingKey($vendorId, self::VENDOR_SETTING_SHOW_ON_CLIENT),
            $show ? '1' : '0',
            'boolean',
            'vendor_services'
        );
    }

    /**
     * Whether area-based (fixed / per m²) pricing is exposed on the client shop app for this product.
     */
    public static function areaPricingVisibleOnClient(Product $product): bool
    {
        if (! self::appliesToProduct($product)) {
            return false;
        }

        $vendorId = (int) ($product->vendor_id ?? 0);
        if ($vendorId > 0) {
            return self::vendorShowAreaPricingOnClient($vendorId);
        }

        return true;
    }

    /**
     * Client shop list/detail — single ON/OFF for fixed / per m² (vendor service-pricing toggle).
     *
     * @return array{show_area_pricing_on_client: bool}
     */
    public static function areaPricingClientVisibilityFields(Product $product): array
    {
        return [
            'show_area_pricing_on_client' => self::areaPricingVisibleOnClient($product),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function clientVisibilityToggleFields(bool $show): array
    {
        return [
            'show_on_client_products_services' => $show,
            'toggle_label' => self::CLIENT_VISIBILITY_TOGGLE_LABEL,
            'toggle_description' => self::CLIENT_VISIBILITY_TOGGLE_DESCRIPTION,
        ];
    }

    public static function vendorIsConfigured(int $vendorId): bool
    {
        return Setting::get(self::vendorSettingKey($vendorId, 'service_pricing_type')) !== null
            || Setting::get(self::vendorSettingKey($vendorId, 'service_pricing_price')) !== null;
    }

    /**
     * @return array{pricing_type: string, price: float, price_includes: array<string, bool>}
     */
    public static function resolveConfigForProduct(Product $product): array
    {
        $vendorId = (int) ($product->vendor_id ?? 0);
        if ($vendorId > 0) {
            return self::vendorConfig($vendorId);
        }

        return self::catalogServiceConfig($product);
    }

    /**
     * Platform (non-vendor) service pricing — from linked Service row (admin /api/admin/services/{id}/settings),
     * not from removed global /api/admin/settings/service-pricing.
     *
     * @return array{pricing_type: string, price: float, price_includes: array<string, bool>}
     */
    public static function catalogServiceConfig(Product $product): array
    {
        $service = self::primaryLinkedService($product);
        if ($service !== null) {
            $type = self::normalizeType($service->pricing_type ?? self::TYPE_FIXED, true);
            $includes = self::normalizeIncludes($service->price_includes, true) ?? self::emptyIncludes();
            $rate = max(0, round((float) ($service->price ?? 0), 2));

            return [
                'pricing_type' => $type,
                'price' => $rate,
                'price_includes' => $includes,
            ];
        }

        $type = self::normalizeType($product->pricing_type ?? self::TYPE_FIXED, true);
        $includes = self::normalizeIncludes($product->price_includes, true) ?? self::emptyIncludes();

        return [
            'pricing_type' => $type,
            'price' => 0.0,
            'price_includes' => $includes,
        ];
    }

    /**
     * Set pricing on a platform service and sync linked type=service products (cart/checkout source of truth).
     *
     * @param  array<string, bool>|null  $includes
     */
    public static function applyServiceCatalogPricing(
        Service $service,
        string $pricingType,
        float $rate,
        ?array $includes = null
    ): int {
        $type = self::normalizeType($pricingType, true);
        $rate = max(0, round($rate, 2));
        $includes = self::normalizeIncludes($includes, true) ?? self::emptyIncludes();

        $service->pricing_type = $type;
        $service->price = $rate;
        $service->price_includes = $includes;
        $service->save();

        $count = 0;
        $service->products()
            ->where('type', 'service')
            ->orderBy('products.id')
            ->chunkById(100, function ($products) use ($type, $includes, &$count) {
                foreach ($products as $product) {
                    /** @var Product $product */
                    $product->pricing_type = $type;
                    $product->price_includes = $includes;
                    $product->save();
                    $count++;
                }
            });

        return $count;
    }

    public static function primaryLinkedService(Product $product): ?Service
    {
        if (! $product->relationLoaded('services')) {
            $product->loadMissing('services');
        }

        if ($product->services->isEmpty()) {
            return null;
        }

        return $product->services->sortBy('id')->first();
    }

    /**
     * @param  array<string, bool>|null  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function saveVendor(
        int $vendorId,
        string $pricingType,
        float $price,
        ?array $includes,
        ?bool $showOnClientProductsServices = null
    ): array {
        $type = self::normalizeType($pricingType, true);
        $price = max(0, round($price, 2));

        return self::saveVendorMultiselect(
            $vendorId,
            $type === self::TYPE_FIXED,
            $type === self::TYPE_PER_M2,
            $type === self::TYPE_FIXED ? $price : 0.0,
            $type === self::TYPE_PER_M2 ? $price : 0.0,
            $includes,
            $showOnClientProductsServices
        );
    }

    /**
     * @param  array<string, bool>|null  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function saveVendorMultiselect(
        int $vendorId,
        bool $enableFixed,
        bool $enablePerM2,
        float $fixedPrice,
        float $perM2Price,
        ?array $includes,
        ?bool $showOnClientProductsServices = null
    ): array {
        $fixedPrice = max(0, round($fixedPrice, 2));
        $perM2Price = max(0, round($perM2Price, 2));
        $includes = self::normalizeIncludes($includes, true) ?? self::emptyIncludes();

        if ($showOnClientProductsServices === true && ! $enableFixed && ! $enablePerM2) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'enable_fixed_price' => ['Enable at least Fixed price or Price per m² when showing on the client app.'],
            ]);
        }

        Setting::set(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_FIXED), $enableFixed ? '1' : '0', 'boolean', 'vendor_services');
        Setting::set(self::vendorSettingKey($vendorId, self::VENDOR_ENABLE_PER_M2), $enablePerM2 ? '1' : '0', 'boolean', 'vendor_services');
        Setting::set(self::vendorSettingKey($vendorId, self::VENDOR_FIXED_AMOUNT), (string) $fixedPrice, 'text', 'vendor_services');
        Setting::set(self::vendorSettingKey($vendorId, self::VENDOR_PER_M2_RATE), (string) $perM2Price, 'text', 'vendor_services');

        $legacyType = $enablePerM2 && ! $enableFixed
            ? self::TYPE_PER_M2
            : self::TYPE_FIXED;
        $legacyPrice = $legacyType === self::TYPE_PER_M2 ? $perM2Price : $fixedPrice;

        Setting::set(self::vendorSettingKey($vendorId, 'service_pricing_type'), $legacyType, 'text', 'vendor_services');
        Setting::set(self::vendorSettingKey($vendorId, 'service_pricing_price'), (string) $legacyPrice, 'text', 'vendor_services');
        Setting::set(self::vendorSettingKey($vendorId, 'service_pricing_includes'), json_encode($includes), 'json', 'vendor_services');

        if ($showOnClientProductsServices !== null) {
            self::saveVendorAreaPricingVisibility($vendorId, $showOnClientProductsServices);
        }

        return self::syncVendorServicesAndProducts($vendorId, $legacyType, $legacyPrice, $includes);
    }

    /**
     * Normalize vendor service-pricing save body (multiselect or legacy radio).
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     enable_fixed: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float,
     *     show_on_client_products_services: bool
     * }
     */
    public static function parseVendorServicePricingSaveInput(array $input, ?int $vendorId = null): array
    {
        $existing = $vendorId !== null && $vendorId > 0
            ? self::vendorConfigExpanded($vendorId)
            : null;

        $show = filter_var($input['show_on_client_products_services'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $hasMultiselect = array_key_exists('enable_fixed_price', $input)
            || array_key_exists('enable_per_m2', $input)
            || array_key_exists('fixed_price', $input)
            || array_key_exists('per_m2_price', $input)
            || array_key_exists('price_per_m2', $input);

        if ($hasMultiselect) {
            $enableFixed = array_key_exists('enable_fixed_price', $input)
                ? filter_var($input['enable_fixed_price'], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($existing['enable_fixed_price'] ?? false);
            $enablePerM2 = array_key_exists('enable_per_m2', $input)
                ? filter_var($input['enable_per_m2'], FILTER_VALIDATE_BOOLEAN)
                : (bool) ($existing['enable_per_m2'] ?? false);

            $fixedPrice = self::resolveVendorFixedPriceFromInput($input, $existing, $enableFixed);
            $perM2Price = self::resolveVendorPerM2PriceFromInput($input, $existing, $enablePerM2);

            return [
                'enable_fixed' => $enableFixed,
                'enable_per_m2' => $enablePerM2,
                'fixed_price' => $fixedPrice,
                'per_m2_price' => $perM2Price,
                'show_on_client_products_services' => $show,
            ];
        }

        $type = self::normalizeType((string) ($input['pricing_type'] ?? self::TYPE_FIXED), true);
        $price = max(0, round((float) ($input['price'] ?? 0), 2));

        // Legacy radio body — if vendor already had both modes, keep the other rate when only one slot is posted.
        if ($existing !== null && ($existing['enable_fixed_price'] ?? false) && ($existing['enable_per_m2'] ?? false)) {
            return [
                'enable_fixed' => true,
                'enable_per_m2' => true,
                'fixed_price' => $type === self::TYPE_FIXED
                    ? $price
                    : (float) ($existing['fixed_price'] ?? 0),
                'per_m2_price' => $type === self::TYPE_PER_M2
                    ? $price
                    : (float) ($existing['per_m2_price'] ?? 0),
                'show_on_client_products_services' => $show,
            ];
        }

        return [
            'enable_fixed' => $type === self::TYPE_FIXED,
            'enable_per_m2' => $type === self::TYPE_PER_M2,
            'fixed_price' => $type === self::TYPE_FIXED ? $price : 0.0,
            'per_m2_price' => $type === self::TYPE_PER_M2 ? $price : 0.0,
            'show_on_client_products_services' => $show,
        ];
    }

    /**
     * Map vendor mobile form fields (single price + toggles) into multiselect save shape.
     */
    public static function hydrateVendorServicePricingRequest(\Illuminate\Http\Request $request, int $vendorId): void
    {
        $existing = self::vendorConfigExpanded($vendorId);

        $hasToggle = $request->has('enable_fixed_price') || $request->has('enable_per_m2');
        $hasAmount = $request->has('fixed_price') || $request->has('per_m2_price')
            || $request->has('price_per_m2') || $request->has('pricePerM2');

        if (! $hasToggle && ! $hasAmount && ! $request->has('pricing_type')) {
            return;
        }

        $enableFixed = $request->has('enable_fixed_price')
            ? filter_var($request->input('enable_fixed_price'), FILTER_VALIDATE_BOOLEAN)
            : (bool) ($existing['enable_fixed_price'] ?? false);
        $enablePerM2 = $request->has('enable_per_m2')
            ? filter_var($request->input('enable_per_m2'), FILTER_VALIDATE_BOOLEAN)
            : (bool) ($existing['enable_per_m2'] ?? false);

        if ($request->has('pricing_type') && ! $request->has('enable_fixed_price') && ! $request->has('enable_per_m2')) {
            $type = self::normalizeType((string) $request->input('pricing_type'), true);
            if ($existing['area_pricing_multiselect'] ?? false) {
                $enableFixed = $type === self::TYPE_FIXED ? true : $enableFixed;
                $enablePerM2 = $type === self::TYPE_PER_M2 ? true : $enablePerM2;
            } else {
                $enableFixed = $type === self::TYPE_FIXED;
                $enablePerM2 = $type === self::TYPE_PER_M2;
            }
        }

        if ($enableFixed || $enablePerM2 || $hasAmount || $hasToggle) {
            $input = $request->all();
            $request->merge([
                'enable_fixed_price' => $enableFixed,
                'enable_per_m2' => $enablePerM2,
                'fixed_price' => self::resolveVendorFixedPriceFromInput($input, $existing, $enableFixed),
                'per_m2_price' => self::resolveVendorPerM2PriceFromInput($input, $existing, $enablePerM2),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $existing
     */
    private static function resolveVendorFixedPriceFromInput(array $input, ?array $existing, bool $enableFixed): float
    {
        if (! $enableFixed) {
            return 0.0;
        }
        if (array_key_exists('fixed_price', $input) && $input['fixed_price'] !== '' && $input['fixed_price'] !== null) {
            return max(0, round((float) $input['fixed_price'], 2));
        }
        if (array_key_exists('price', $input) && $input['price'] !== '' && $input['price'] !== null) {
            $type = self::normalizeType((string) ($input['pricing_type'] ?? self::TYPE_FIXED), true);
            if ($type === self::TYPE_FIXED) {
                return max(0, round((float) $input['price'], 2));
            }
        }

        return max(0, round((float) ($existing['fixed_price'] ?? 0), 2));
    }

    /**
     * @param  array<string, mixed>|null  $existing
     */
    private static function resolveVendorPerM2PriceFromInput(array $input, ?array $existing, bool $enablePerM2): float
    {
        if (! $enablePerM2) {
            return 0.0;
        }
        foreach (['per_m2_price', 'price_per_m2', 'pricePerM2', 'per_sqm_price'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '' && $input[$key] !== null) {
                return max(0, round((float) $input[$key], 2));
            }
        }
        if (array_key_exists('price', $input) && $input['price'] !== '' && $input['price'] !== null) {
            $type = self::normalizeType((string) ($input['pricing_type'] ?? ''), true);
            if ($type === self::TYPE_PER_M2) {
                return max(0, round((float) $input['price'], 2));
            }
        }

        return max(0, round((float) ($existing['per_m2_price'] ?? 0), 2));
    }

    /**
     * @param  array<string, bool>|null  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function saveVendorPriceIncludesOnly(int $vendorId, ?array $includes): array
    {
        $config = self::vendorConfig($vendorId);
        $includes = self::normalizeIncludes($includes, true) ?? self::emptyIncludes();
        Setting::set(
            self::vendorSettingKey($vendorId, 'service_pricing_includes'),
            json_encode($includes),
            'json',
            'vendor_services'
        );

        return self::syncVendorServicesAndProducts($vendorId, $config['pricing_type'], $config['price'], $includes);
    }

    /**
     * @param  array<string, bool>  $includes
     * @return array{synced_services: int, synced_products: int}
     */
    public static function syncVendorServicesAndProducts(int $vendorId, string $pricingType, float $price, array $includes): array
    {
        $servicesUpdated = 0;
        Service::query()->where('vendor_id', $vendorId)->orderBy('id')->chunkById(100, function ($services) use ($pricingType, $includes, &$servicesUpdated) {
            foreach ($services as $service) {
                $service->pricing_type = $pricingType;
                $service->price_includes = $includes;
                $service->save();
                $servicesUpdated++;
            }
        });

        $productsUpdated = 0;
        Product::query()
            ->where('vendor_id', $vendorId)
            ->orderBy('id')
            ->chunkById(100, function ($products) use ($pricingType, $includes, &$productsUpdated) {
                foreach ($products as $product) {
                    $product->pricing_type = $pricingType;
                    $product->price_includes = $includes;
                    $product->save();
                    $productsUpdated++;
                }
            });

        return [
            'synced_services' => $servicesUpdated,
            'synced_products' => $productsUpdated,
        ];
    }

    /**
     * Vendor mobile settings payload (mirrors admin global screen).
     *
     * @return array<string, mixed>
     */
    public static function vendorAdminApiPayload(int $vendorId): array
    {
        $expanded = self::vendorConfigExpanded($vendorId);
        $shim = new Product([
            'name' => 'Vendor Services',
            'type' => 'service',
            'price' => 0,
            'pricing_type' => $expanded['pricing_type'],
            'price_includes' => $expanded['price_includes'],
            'vendor_id' => $vendorId,
        ]);
        $base = self::productSettingsApi($shim);

        return array_merge($base, [
            'vendor_id' => $vendorId,
            'product_id' => null,
            'product_name' => null,
            'service_id' => null,
            'service_name' => null,
            'applies_to' => 'vendor_catalog_products',
            'settings_available' => true,
            'is_service' => true,
            'configured' => self::vendorIsConfigured($vendorId),
            'note' => 'Applies to all of this vendor’s shop products (type product and service). Platform-only SKUs are excluded.',
            // Canonical vendor rates — always expose both slots (use enable_* + amount; legacy `price` is single-mode only).
            'pricing_type' => $expanded['pricing_type'],
            'area_pricing_multiselect' => $expanded['area_pricing_multiselect'],
            'enable_fixed_price' => $expanded['enable_fixed_price'],
            'enable_per_m2' => $expanded['enable_per_m2'],
            'fixed_price' => round((float) $expanded['fixed_price'], 2),
            'per_m2_price' => round((float) $expanded['per_m2_price'], 2),
            'fixed_price_amount' => $expanded['enable_fixed_price']
                ? round((float) $expanded['fixed_price'], 2)
                : null,
            'price_per_m2' => $expanded['enable_per_m2']
                ? round((float) $expanded['per_m2_price'], 2)
                : null,
            'price' => self::vendorServicePricingLegacyPriceField($expanded),
            'area_pricing_settings' => self::vendorAreaPricingSettingsBlock($expanded),
        ], self::clientVisibilityToggleFields(self::vendorShowAreaPricingOnClient($vendorId)));
    }

    /**
     * @param  array{
     *     pricing_type: string,
     *     area_pricing_multiselect: bool,
     *     enable_fixed_price: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float
     * }  $expanded
     */
    public static function vendorAreaPricingSettingsBlock(array $expanded): array
    {
        return [
            'multiselect' => (bool) ($expanded['area_pricing_multiselect'] ?? false),
            'fixed' => [
                'enabled' => (bool) ($expanded['enable_fixed_price'] ?? false),
                'amount' => round((float) ($expanded['fixed_price'] ?? 0), 2),
            ],
            'per_m2' => [
                'enabled' => (bool) ($expanded['enable_per_m2'] ?? false),
                'rate' => round((float) ($expanded['per_m2_price'] ?? 0), 2),
            ],
        ];
    }

    /**
     * @param  array{
     *     area_pricing_multiselect: bool,
     *     enable_fixed_price: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float
     * }  $expanded
     */
    public static function vendorServicePricingLegacyPriceField(array $expanded): ?float
    {
        if ($expanded['area_pricing_multiselect'] ?? false) {
            return null;
        }
        if ($expanded['enable_per_m2'] ?? false) {
            return round((float) ($expanded['per_m2_price'] ?? 0), 2);
        }
        if ($expanded['enable_fixed_price'] ?? false) {
            return round((float) ($expanded['fixed_price'] ?? 0), 2);
        }

        return null;
    }

    /**
     * Admin Settings screen payload (no id) — same UI as Product Settings.
     *
     * @return array<string, mixed>
     */
    public static function globalAdminApiPayload(): array
    {
        $config = self::globalConfig();
        $shim = new Product([
            'name' => 'All Services',
            'type' => 'service',
            'price' => $config['price'],
            'pricing_type' => $config['pricing_type'],
            'price_includes' => $config['price_includes'],
        ]);
        $base = self::productSettingsApi($shim);

        return array_merge($base, [
            'product_id' => null,
            'product_name' => null,
            'service_id' => null,
            'service_name' => null,
            'applies_to' => 'all_services',
            'settings_available' => true,
            'is_service' => true,
            'note' => 'Per m²: rate × area at checkout. Fixed: customer pays (product base price + Fixed amount), e.g. base 100 + Fixed 800 = 900. Shop products keep Instant Order Fee.',
        ]);
    }

    public static function normalizeType(?string $type, bool $isService): string
    {
        $type = strtolower(trim((string) $type));
        if (! $isService) {
            return self::TYPE_FIXED;
        }
        if (in_array($type, [self::TYPE_PER_M2, 'per_sqm', 'sqm', 'm2', 'area'], true)) {
            return self::TYPE_PER_M2;
        }

        return self::TYPE_FIXED;
    }

    /**
     * Whether area / tree vendor pricing applies to this catalog line on the client shop.
     *
     * - Platform: type=service OR linked via product_service pivot (not plain category SKUs).
     * - Vendor marketplace: every product with vendor_id (type product or service) uses vendor settings.
     */
    public static function appliesToProduct(Product $product): bool
    {
        $vendorId = (int) ($product->vendor_id ?? 0);
        if ($vendorId > 0) {
            return true;
        }

        $type = strtolower(trim((string) ($product->type ?? '')));
        if ($type === 'service') {
            return true;
        }

        if (! $product->relationLoaded('services')) {
            $product->loadMissing('services');
        }

        return $product->services->isNotEmpty();
    }

    public static function isPerM2(Product $product, ?string $areaPricingMode = null): bool
    {
        if (! self::appliesToProduct($product)) {
            return false;
        }

        if (! self::areaPricingVisibleOnClient($product)) {
            return false;
        }

        $normalized = self::normalizeAreaPricingMode($areaPricingMode);
        if ($normalized !== null) {
            return $normalized === self::TYPE_PER_M2;
        }

        $modes = self::areaModesForProduct($product);

        return $modes['per_m2'] && ! $modes['fixed'];
    }

    /**
     * Live per-m² rate for checkout (vendor setting when product is vendor-owned).
     */
    public static function perM2Rate(?Product $product = null): float
    {
        if ($product instanceof Product) {
            return max(0, round((float) self::areaModesForProduct($product)['per_m2_rate'], 2));
        }

        return max(0, round((float) self::globalConfig()['price'], 2));
    }

    /**
     * Effective unit price for a catalog product (cart / Buy Now without area).
     * Per m² services: configured product catalog price (area × per-m² rate is applied in lineTotal).
     * Fixed services: product base (catalog) price + global Fixed amount.
     */
    public static function effectiveUnitPrice(Product $product, float $fallbackProductPrice, ?string $areaPricingMode = null): float
    {
        if (! self::appliesToProduct($product)) {
            return round($fallbackProductPrice, 2);
        }

        if (! self::areaPricingVisibleOnClient($product)) {
            return round($fallbackProductPrice, 2);
        }

        $mode = self::normalizeAreaPricingMode($areaPricingMode) ?? self::implicitAreaPricingMode($product);
        if ($mode === self::TYPE_PER_M2) {
            return round($fallbackProductPrice, 2);
        }

        $modes = self::areaModesForProduct($product);

        return round($fallbackProductPrice + (float) $modes['fixed_amount'], 2);
    }

    /**
     * Whether the global service-pricing setting has been configured by admin.
     */
    public static function globalIsConfigured(): bool
    {
        $config = self::globalConfig();

        return $config['pricing_type'] === self::TYPE_PER_M2 || $config['price'] > 0;
    }

    /**
     * Normalize price_includes from request (object, array of keys, or JSON string).
     *
     * @param  mixed  $raw
     * @return array<string, bool>|null null when not a service / not provided meaningfully
     */
    public static function normalizeIncludes(mixed $raw, bool $isService): ?array
    {
        if (! $isService) {
            return null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw)) {
            return self::emptyIncludes();
        }

        // Accept ["materials","labor"] style
        $isList = array_keys($raw) === range(0, count($raw) - 1);
        if ($isList) {
            $selected = [];
            foreach ($raw as $key) {
                $k = strtolower(trim((string) $key));
                if (in_array($k, self::INCLUDE_KEYS, true)) {
                    $selected[$k] = true;
                }
            }
            $out = self::emptyIncludes();
            foreach ($out as $key => $_) {
                $out[$key] = (bool) ($selected[$key] ?? false);
            }

            return $out;
        }

        $out = self::emptyIncludes();
        foreach (self::INCLUDE_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
            } elseif (array_key_exists('includes_'.$key, $raw)) {
                $out[$key] = filter_var($raw['includes_'.$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }

    /**
     * @return array<string, bool>
     */
    public static function emptyIncludes(): array
    {
        return array_fill_keys(self::INCLUDE_KEYS, false);
    }

    /**
     * @param  array<string, bool>|null  $includes
     * @return list<string>
     */
    public static function includeLabels(?array $includes): array
    {
        if (! is_array($includes)) {
            return [];
        }
        $labels = [];
        foreach (self::INCLUDE_KEYS as $key) {
            if (! empty($includes[$key])) {
                $labels[] = self::INCLUDE_LABELS[$key];
            }
        }

        return $labels;
    }

    public static function formatMoney(float $amount): string
    {
        $formatted = number_format($amount, 2, '.', ',');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return 'AED '.$formatted;
    }

    /**
     * Fixed mode — product detail breakdown lines only (no + / total in preview JSON).
     *
     * @return array<string, mixed>
     */
    public static function fixedModeBreakdownDisplay(float $catalogPrice, float $fixedAddon): array
    {
        $catalogPrice = round($catalogPrice, 2);
        $fixedAddon = round($fixedAddon, 2);

        return [
            'display_only' => true,
            'do_not_add_lines' => true,
            'catalog_price' => $catalogPrice,
            'catalog_price_label' => self::formatMoney($catalogPrice),
            'fixed_price' => $fixedAddon,
            'fixed_price_label' => self::formatMoney($fixedAddon),
            'lines' => [
                [
                    'key' => 'catalog_price',
                    'label' => 'Product price',
                    'amount' => $catalogPrice,
                    'amount_label' => self::formatMoney($catalogPrice),
                ],
                [
                    'key' => 'fixed_price',
                    'label' => 'Fixed price',
                    'amount' => $fixedAddon,
                    'amount_label' => self::formatMoney($fixedAddon),
                ],
            ],
            'note' => 'Show these two amounts separately on the product screen. Do not display a summed total in the breakdown.',
        ];
    }

    /**
     * Customer detail — fixed mode (checkout total is separate from display breakdown).
     *
     * @return array<string, mixed>
     */
    public static function customerPreviewFixed(float $catalogPrice, float $fixedAddon): array
    {
        $fixedAddon = round($fixedAddon, 2);
        $catalogPrice = round($catalogPrice, 2);
        $checkoutTotal = round($catalogPrice + $fixedAddon, 2);

        return [
            'mode' => self::TYPE_FIXED,
            'catalog_price_label' => self::formatMoney($catalogPrice),
            'fixed_price_label' => self::formatMoney($fixedAddon),
            'checkout_total' => $checkoutTotal,
            'checkout_total_label' => self::formatMoney($checkoutTotal),
            'breakdown' => self::fixedModeBreakdownDisplay($catalogPrice, $fixedAddon),
            'note' => 'Breakdown is display-only. Use checkout_price / checkout_total when the customer pays (catalog + fixed addon).',
        ];
    }

    /**
     * Customer detail — per m² mode preview (catalog list + rate × area at checkout).
     *
     * @return array<string, mixed>
     */
    public static function customerPreviewPerM2(float $catalogPrice, float $rate): array
    {
        $catalogPrice = round($catalogPrice, 2);
        $rate = round($rate, 2);
        $exampleArea = 100.0;
        $exampleTotal = round($exampleArea * $rate, 2);

        return [
            'mode' => self::TYPE_PER_M2,
            'price_display' => 'Listed: '.self::formatMoney($catalogPrice),
            'checkout_rate_display' => 'Checkout rate: '.self::formatMoney($rate).' / m²',
            'note' => 'Per m²: optional area at checkout. With area: rate × m². Without area: catalog price only.',
            'example' => [
                'area' => $exampleArea,
                'price_per_m2' => $rate,
                'total' => $exampleTotal,
                'total_label' => self::formatMoney($exampleTotal),
            ],
        ];
    }

    /**
     * Product-detail breakdown when both vendor area prices are on — show each amount only (never sum fixed + per m²).
     *
     * @return array<string, mixed>
     */
    public static function vendorAreaPricesBreakdownDisplay(float $catalogPrice, float $fixedAddon, float $rate): array
    {
        $catalogPrice = round($catalogPrice, 2);
        $fixedAddon = round($fixedAddon, 2);
        $rate = round($rate, 2);

        return [
            'display_only' => true,
            'do_not_add_lines' => true,
            'catalog_price' => $catalogPrice,
            'catalog_price_label' => self::formatMoney($catalogPrice),
            'fixed_price' => $fixedAddon,
            'fixed_price_label' => self::formatMoney($fixedAddon),
            'price_per_m2' => $rate,
            'price_per_m2_label' => self::formatMoney($rate).' / m²',
            'lines' => [
                [
                    'key' => 'catalog_price',
                    'label' => 'Product price',
                    'amount' => $catalogPrice,
                    'amount_label' => self::formatMoney($catalogPrice),
                ],
                [
                    'key' => 'fixed_price',
                    'label' => 'Fixed price',
                    'amount' => $fixedAddon,
                    'amount_label' => self::formatMoney($fixedAddon),
                ],
                [
                    'key' => 'price_per_m2',
                    'label' => 'Price per m²',
                    'amount' => $rate,
                    'amount_label' => self::formatMoney($rate).' / m²',
                ],
            ],
            'note' => 'Show these prices separately on the product screen. Do not add fixed price and per m² rate together.',
        ];
    }

    /**
     * Customer detail when vendor enabled both fixed and per m² — separate previews, no blended totals.
     *
     * @return array<string, mixed>
     */
    public static function customerPreviewMulti(float $catalogPrice, float $fixedAddon, float $rate): array
    {
        $breakdown = self::vendorAreaPricesBreakdownDisplay($catalogPrice, $fixedAddon, $rate);

        return [
            'pricing_type' => 'multi',
            'list_price_display' => 'Listed: '.self::formatMoney(round($catalogPrice, 2)),
            'breakdown' => $breakdown,
            'selection_note' => 'Customer picks Fixed price or Price per m², then adds to cart with area_pricing_mode. Only the chosen mode applies at checkout.',
            'fixed' => self::customerPreviewFixed($catalogPrice, $fixedAddon),
            'per_m2' => self::customerPreviewPerM2($catalogPrice, $rate),
            'when_fixed_selected' => self::customerPreviewFixed($catalogPrice, $fixedAddon),
            'when_per_m2_selected' => self::customerPreviewPerM2($catalogPrice, $rate),
        ];
    }

    /**
     * Customer / admin product payload fields for pricing UI.
     *
     * Store listing uses `price` = catalog product price (never replaced by global rate).
     * Per m² checkout uses `price_per_m2` from linked service (or vendor settings).
     *
     * @return array<string, mixed>
     */
    public static function productApiFields(Product $product): array
    {
        $isService = self::appliesToProduct($product);
        $catalogPrice = round((float) $product->price, 2);

        if (! $isService) {
            return [
                ...self::areaPricingClientVisibilityFields($product),
                'pricing_type' => self::TYPE_FIXED,
                'price' => $catalogPrice,
                'catalog_price' => $catalogPrice,
                'price_per_m2' => null,
                'currency' => 'AED',
                'price_unit' => null,
                'price_label' => self::formatMoney($catalogPrice),
                'requires_area' => false,
                'price_includes' => null,
                'price_includes_labels' => [],
                'customer_preview' => null,
                ...ServiceTreePricing::productApiFields($product),
            ];
        }

        if (! self::areaPricingVisibleOnClient($product)) {
            return [
                ...self::areaPricingClientVisibilityFields($product),
                'pricing_type' => self::TYPE_FIXED,
                'price' => $catalogPrice,
                'catalog_price' => $catalogPrice,
                'price_per_m2' => null,
                'currency' => 'AED',
                'price_unit' => null,
                'price_label' => self::formatMoney($catalogPrice),
                'requires_area' => false,
                'price_includes' => null,
                'price_includes_labels' => [],
                'customer_preview' => null,
                ...ServiceTreePricing::productApiFields($product),
            ];
        }

        $config = self::resolveConfigForProduct($product);
        $includes = $config['price_includes'];
        $modes = self::areaModesForProduct($product);
        $multiselect = self::requiresAreaPricingModeSelection($product);

        if ($multiselect) {
            $fixedAddon = round((float) $modes['fixed_amount'], 2);
            $rate = round((float) $modes['per_m2_rate'], 2);
            $fixedTotal = round($catalogPrice + $fixedAddon, 2);
            $preview = self::customerPreviewMulti($catalogPrice, $fixedAddon, $rate);

            return [
                ...self::areaPricingClientVisibilityFields($product),
                'pricing_type' => 'multi',
                'area_pricing_mode_required' => true,
                'area_pricing_options' => [
                    'fixed' => [
                        'enabled' => true,
                        'mode' => self::TYPE_FIXED,
                        'catalog_price' => $catalogPrice,
                        'fixed_price_addon' => $fixedAddon,
                        'checkout_price' => $fixedTotal,
                        'price_label' => self::formatMoney($fixedAddon),
                        'price_display_note' => 'Fixed addon only on breakdown; at checkout: catalog + fixed addon.',
                        'customer_preview' => $preview['when_fixed_selected'],
                    ],
                    'per_m2' => [
                        'enabled' => true,
                        'mode' => self::TYPE_PER_M2,
                        'catalog_price' => $catalogPrice,
                        'price_per_m2' => $rate,
                        'price_label' => self::formatMoney($rate).' / m²',
                        'unit_rate_label' => self::formatMoney($rate).' / m²',
                        'customer_preview' => $preview['when_per_m2_selected'],
                    ],
                ],
                'price_breakdown' => $preview['breakdown'],
                // List/card: catalog only — checkout totals live under area_pricing_options + customer_preview.
                'price' => $catalogPrice,
                'catalog_price' => $catalogPrice,
                'base_price' => $catalogPrice,
                'price_label' => self::formatMoney($catalogPrice),
                'list_price_label' => self::formatMoney($catalogPrice),
                'enable_fixed_price' => true,
                'enable_per_m2' => true,
                'fixed_price_addon' => $fixedAddon,
                'fixed_price' => $fixedAddon,
                'per_m2_price' => $rate,
                'checkout_price' => null,
                'price_per_m2' => $rate,
                'unit_rate' => $rate,
                'unit_rate_label' => self::formatMoney($rate).' / m²',
                'checkout_rate_label' => self::formatMoney($rate).' / m²',
                'currency' => 'AED',
                'price_unit' => null,
                'requires_area' => false,
                'area_optional' => null,
                'price_includes' => $includes,
                'price_includes_labels' => self::includeLabels($includes),
                'customer_preview' => $preview,
                ...ServiceTreePricing::productApiFields($product),
            ];
        }

        $type = $modes['per_m2'] && ! $modes['fixed'] ? self::TYPE_PER_M2 : self::TYPE_FIXED;
        $rate = $modes['per_m2'] ? $modes['per_m2_rate'] : $modes['fixed_amount'];

        if ($type === self::TYPE_PER_M2) {
            return [
                ...self::areaPricingClientVisibilityFields($product),
                'pricing_type' => self::TYPE_PER_M2,
                // Store/list card: each product's own catalog price (never the global m² rate).
                'price' => $catalogPrice,
                'catalog_price' => $catalogPrice,
                'price_label' => self::formatMoney($catalogPrice),
                'list_price_label' => self::formatMoney($catalogPrice),
                // Checkout: service (or vendor) rate × required area.
                'price_per_m2' => $rate,
                'unit_rate' => $rate,
                'unit_rate_label' => self::formatMoney($rate).' / m²',
                'checkout_rate_label' => self::formatMoney($rate).' / m²',
                'currency' => 'AED',
                'price_unit' => 'm²',
                'requires_area' => false,
                'area_optional' => true,
                'price_includes' => $includes,
                'price_includes_labels' => self::includeLabels($includes),
                'customer_preview' => self::customerPreviewPerM2($catalogPrice, $rate),
                ...ServiceTreePricing::productApiFields($product),
            ];
        }

        // Fixed: customer pays product base + service fixed addon (e.g. 100 + 800 = 900).
        $fixedAddon = round((float) $rate, 2);
        $total = round($catalogPrice + $fixedAddon, 2);

        return [
            ...self::areaPricingClientVisibilityFields($product),
            'pricing_type' => self::TYPE_FIXED,
            'price' => $total,
            'catalog_price' => $catalogPrice,
            'base_price' => $catalogPrice,
            'fixed_price_addon' => $fixedAddon,
            'fixed_price' => $fixedAddon,
            'checkout_price' => $total,
            'price_per_m2' => null,
            'currency' => 'AED',
            'price_unit' => null,
            'price_label' => self::formatMoney($total),
            'requires_area' => false,
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'customer_preview' => self::customerPreviewFixed($catalogPrice, $fixedAddon),
            ...ServiceTreePricing::productApiFields($product),
        ];
    }

    /**
     * Service product list/card payload — same pricing labels as {@see productApiFields}
     * but omits heavy nested blocks (customer_preview, price_includes, tree_pricing UI).
     *
     * @return array<string, mixed>
     */
    public static function productApiFieldsForList(Product $product): array
    {
        $fields = self::productApiFields($product);

        unset(
            $fields['customer_preview'],
            $fields['price_includes'],
            $fields['tree_pricing'],
        );

        return $fields;
    }

    /**
     * Full Product Settings screen payload (admin mobile UI).
     *
     * @return array<string, mixed>
     */
    public static function productSettingsApi(Product $product): array
    {
        $isService = self::appliesToProduct($product);
        $fields = self::productApiFields($product);
        $includes = is_array($fields['price_includes'] ?? null)
            ? $fields['price_includes']
            : self::emptyIncludes();

        $includeOptions = [];
        foreach (self::INCLUDE_KEYS as $key) {
            $includeOptions[] = [
                'key' => $key,
                'label' => self::INCLUDE_LABELS[$key],
                'selected' => (bool) ($includes[$key] ?? false),
            ];
        }

        $vendorId = (int) ($product->vendor_id ?? 0);
        $vendorExpanded = $vendorId > 0 ? self::vendorConfigExpanded($vendorId) : null;
        $type = $vendorExpanded['pricing_type'] ?? ($fields['pricing_type'] ?? self::TYPE_FIXED);
        $enableFixed = $vendorExpanded['enable_fixed_price'] ?? ($type !== self::TYPE_PER_M2);
        $enablePerM2 = $vendorExpanded['enable_per_m2'] ?? ($type === self::TYPE_PER_M2);
        $fixedPrice = $vendorExpanded['fixed_price'] ?? ($type === self::TYPE_FIXED ? (float) self::resolveConfigForProduct($product)['price'] : 0.0);
        $perM2Price = $vendorExpanded['per_m2_price'] ?? ($type === self::TYPE_PER_M2 ? (float) self::resolveConfigForProduct($product)['price'] : 0.0);
        // Legacy single `price` — one active slot; multiselect apps must use fixed_price + per_m2_price.
        $price = $isService
            ? ($type === 'multi'
                ? round((float) $fixedPrice, 2)
                : round((float) self::resolveConfigForProduct($product)['price'], 2))
            : round((float) ($product->price ?? 0), 2);

        return [
            'product_id' => (int) $product->id,
            'product_name' => (string) $product->name,
            'type' => $product->type ?? 'product',
            'is_service' => $isService,
            'settings_available' => $isService,
            'pricing_type' => $type,
            'area_pricing_multiselect' => (bool) ($vendorExpanded['area_pricing_multiselect'] ?? false),
            'enable_fixed_price' => (bool) $enableFixed,
            'enable_per_m2' => (bool) $enablePerM2,
            'fixed_price' => round((float) $fixedPrice, 2),
            'per_m2_price' => round((float) $perM2Price, 2),
            'pricing_type_options' => [
                [
                    'value' => self::TYPE_FIXED,
                    'label' => 'Fixed Price',
                    'description' => 'This amount is added to each service product base price (e.g. base 100 + Fixed 800 = 900).',
                    'selected' => (bool) $enableFixed,
                    'multiselect' => true,
                ],
                [
                    'value' => self::TYPE_PER_M2,
                    'label' => 'Price per Square Meter (m²)',
                    'description' => 'Enter the unit price. Customers will enter the required area manually.',
                    'selected' => (bool) $enablePerM2,
                    'multiselect' => true,
                ],
            ],
            'price' => $type === 'multi' ? null : $price,
            'fixed_price_amount' => $enableFixed ? round((float) $fixedPrice, 2) : null,
            'price_per_m2' => $enablePerM2 ? round((float) $perM2Price, 2) : null,
            'per_m2_price' => $enablePerM2 ? round((float) $perM2Price, 2) : null,
            'currency' => 'AED',
            'price_unit' => $type === self::TYPE_PER_M2 ? 'm²' : ($type === 'multi' ? null : null),
            'price_input_label' => $type === self::TYPE_PER_M2
                ? 'Price per m²'
                : ($type === 'multi' ? 'Fixed addon and per m² rate (enable either or both)' : 'Fixed price (added to product base)'),
            'price_input_suffix' => $type === self::TYPE_PER_M2 ? 'AED / m²' : 'AED',
            'price_label' => $type === 'multi'
                ? null
                : ($type === self::TYPE_FIXED
                    ? self::formatMoney($price).' addon'
                    : ($fields['price_label'] ?? self::formatMoney($price))),
            'requires_area' => (bool) ($fields['requires_area'] ?? false),
            'price_includes' => $includes,
            'price_includes_options' => $includeOptions,
            'price_includes_labels' => self::includeLabels($includes),
            'customer_preview' => $fields['customer_preview'] ?? null,
            'example_calculation' => $type === 'multi' ? [
                'title' => 'Example calculations',
                'fixed' => [
                    'base' => 100,
                    'fixed_addon' => round((float) $fixedPrice, 2),
                    'total' => round(100 + (float) $fixedPrice, 2),
                    'total_label' => self::formatMoney(round(100 + (float) $fixedPrice, 2)),
                ],
                'per_m2' => [
                    'area' => 100,
                    'price_per_m2' => round((float) $perM2Price, 2),
                    'total' => round(100 * (float) $perM2Price, 2),
                    'total_label' => self::formatMoney(round(100 * (float) $perM2Price, 2)),
                ],
            ] : ($type === self::TYPE_PER_M2 ? [
                'title' => 'Example calculation',
                'area' => 100,
                'area_label' => '100 m²',
                'price_per_m2' => $price,
                'price_per_m2_label' => self::formatMoney($price),
                'total' => round(100 * $price, 2),
                'total_label' => self::formatMoney(round(100 * $price, 2)),
            ] : [
                'title' => 'Example calculation',
                'base' => 100,
                'fixed_addon' => $price,
                'total' => round(100 + $price, 2),
                'total_label' => self::formatMoney(round(100 + $price, 2)),
                'formula' => '100 + '.$price.' = '.round(100 + $price, 2),
            ]),
            'message' => $isService
                ? null
                : 'Area-based Product Settings apply only to services. Shop products always use Fixed Price.',
        ];
    }

    public static function normalizeArea(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            // Accept "7", "7.5", "7 m²", "7m2", "7 sqm", "7,5"
            $value = str_replace(',', '.', $value);
            $value = preg_replace('/\s*(m²|m2|sq\.?\s*m|sqm|square\s*meters?)\s*$/iu', '', $value);
            $value = trim((string) $value);
        }

        if (! is_numeric($value)) {
            return null;
        }
        $area = round((float) $value, 2);

        return $area > 0 ? $area : null;
    }

    /**
     * Resolve Required Area (m²) from common client/app field names.
     * Accepts: required_area, requiredArea, area, area_m2, areaM2, m2, square_meters.
     * Fallback for per-m² services: some apps put area into `quantity`.
     */
    public static function resolveAreaFromRequest(\Illuminate\Http\Request $request, bool $allowQuantityFallback = true): mixed
    {
        foreach ([
            'required_area',
            'requiredArea',
            'area',
            'area_m2',
            'areaM2',
            'm2',
            'square_meters',
            'squareMeters',
            'required_area_m2',
            'requiredAreaM2',
            'required_area_sqm',
            'areaValue',
            'area_value',
        ] as $key) {
            if ($request->exists($key) && $request->input($key) !== null && $request->input($key) !== '') {
                return $request->input($key);
            }
        }

        // Nested payloads: { pricing: { required_area: 7 } }
        foreach (['pricing', 'service_pricing', 'area_input', 'data'] as $nestKey) {
            $nested = $request->input($nestKey);
            if (! is_array($nested)) {
                continue;
            }
            foreach (['required_area', 'requiredArea', 'area', 'area_m2', 'm2', 'value'] as $key) {
                if (array_key_exists($key, $nested) && $nested[$key] !== null && $nested[$key] !== '') {
                    return $nested[$key];
                }
            }
        }

        if ($allowQuantityFallback && $request->filled('quantity')) {
            $qty = $request->input('quantity');
            // Skip default cart quantity=1 — that is almost never an area.
            if (is_numeric($qty) && (float) $qty >= 0.01 && (float) $qty !== 1.0) {
                return $qty;
            }
        }

        return null;
    }

    /**
     * Resolve area from a cart/buy-now line array (items[]).
     *
     * @param  array<string, mixed>  $row
     */
    public static function resolveAreaFromArray(array $row): mixed
    {
        foreach ([
            'required_area',
            'requiredArea',
            'area',
            'area_m2',
            'areaM2',
            'm2',
            'square_meters',
            'squareMeters',
        ] as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    public static function validateAreaMessage(Product $product, mixed $areaRaw, ?string $areaPricingMode = null): ?string
    {
        if (! self::isPerM2($product, $areaPricingMode)) {
            return null;
        }

        // Area (m²) is optional — customer may continue with service price only.
        if ($areaRaw === null || $areaRaw === '') {
            return null;
        }

        $area = self::normalizeArea($areaRaw);
        if ($area === null) {
            return 'Area (m²) must be a positive number when provided (e.g. 50, 100, 137.5). Send it as required_area (or area / requiredArea).';
        }

        return null;
    }

    /**
     * Remember area entered on Product Details / order-summary for Buy Now pay.
     * Mobile often omits required_area on payment-intent even after the user typed it.
     */
    public static function rememberBuyNowArea(int $userId, int $productId, ?float $area): void
    {
        $normalized = self::normalizeArea($area);
        if ($userId <= 0 || $productId <= 0 || $normalized === null) {
            return;
        }

        \Illuminate\Support\Facades\Cache::put(
            self::buyNowAreaCacheKey($userId, $productId),
            $normalized,
            now()->addHours(6)
        );
    }

    public static function recallBuyNowArea(int $userId, int $productId): ?float
    {
        if ($userId <= 0 || $productId <= 0) {
            return null;
        }

        return self::normalizeArea(
            \Illuminate\Support\Facades\Cache::get(self::buyNowAreaCacheKey($userId, $productId))
        );
    }

    public static function buyNowAreaCacheKey(int $userId, int $productId): string
    {
        return 'shop:buy_now_area:'.$userId.':'.$productId;
    }

    /**
     * Buy Now / payment-intent often omit required_area even after the user entered it
     * on Product Details (and/or it was saved on a cart line). Hydrate from aliases first,
     * then cart row, then remembered Buy Now area from a prior summary call.
     */
    public static function hydrateMissingAreaOntoRequest(
        \Illuminate\Http\Request $request,
        int $userId,
        ?Product $product = null
    ): void {
        $resolved = self::resolveAreaFromRequest($request, false);
        $normalized = self::normalizeArea($resolved);
        if ($normalized !== null) {
            if (! $request->filled('required_area')) {
                $request->merge(['required_area' => $normalized]);
            }
            $productId = (int) ($request->input('product_id') ?? ($product?->id ?? 0));
            if ($productId > 0) {
                self::rememberBuyNowArea($userId, $productId, $normalized);
            }

            return;
        }

        $productId = (int) ($request->input('product_id') ?? 0);
        if ($productId <= 0) {
            return;
        }

        if ($product === null) {
            $product = Product::query()->find($productId);
        }
        if ($product === null || ! self::isPerM2($product)) {
            return;
        }

        $area = \App\Models\Cart::query()
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereNotNull('required_area')
            ->where('required_area', '>', 0)
            ->orderByDesc('id')
            ->value('required_area');

        $normalized = self::normalizeArea($area);
        if ($normalized === null) {
            $normalized = self::recallBuyNowArea($userId, $productId);
        }

        if ($normalized !== null) {
            $request->merge(['required_area' => $normalized]);
            self::rememberBuyNowArea($userId, $productId, $normalized);
        }
    }

    /**
     * Line total for a cart/order line.
     * Fixed: quantity × unitPrice (catalog + fixed addon / options).
     * Per m² with area: required_area × live global per-m² rate.
     * Per m² without area: quantity × configured product price (unitPrice / catalog) — never the m² rate alone.
     * Optional tree/palm quantities add (qty × admin unit price) on top of the base service total.
     */
    public static function lineTotal(
        Product $product,
        float $unitPrice,
        int $quantity,
        ?float $requiredArea,
        ?int $treeQuantity = null,
        ?int $palmTreeQuantity = null,
        ?float $pricePerTree = null,
        ?float $pricePerPalmTree = null,
        ?string $areaPricingMode = null
    ): float {
        if (self::isPerM2($product, $areaPricingMode)) {
            if ($requiredArea !== null && $requiredArea > 0) {
                // Always use live admin per-m² rate (ignore stale cart unit_price).
                $base = round($requiredArea * self::perM2Rate($product), 2);
            } else {
                // Area skipped → configured product/catalog price (unitPrice from effectiveUnitPrice).
                $catalog = round((float) $product->price, 2);
                $m2Rate = self::perM2Rate($product);
                // Guard stale carts that still stored the old per-m² rate as unit_price.
                $price = ($unitPrice > 0 && abs($unitPrice - $m2Rate) > 0.001)
                    ? $unitPrice
                    : $catalog;
                $base = round(max(1, $quantity) * $price, 2);
            }
        } else {
            $base = round(max(1, $quantity) * $unitPrice, 2);
        }

        if (! self::appliesToProduct($product)) {
            return $base;
        }

        if ($treeQuantity !== null || $palmTreeQuantity !== null) {
            $treeConfig = ServiceTreePricing::resolveConfigForProduct($product);
            if ($pricePerTree === null) {
                $pricePerTree = $treeConfig['price_per_tree'];
            }
            if ($pricePerPalmTree === null) {
                $pricePerPalmTree = $treeConfig['price_per_palm_tree'];
            }
        }

        return round(
            $base + ServiceTreePricing::addonTotal($treeQuantity, $palmTreeQuantity, $pricePerTree, $pricePerPalmTree),
            2
        );
    }

    /**
     * Quantity stored on cart/order for per-m² lines (always 1 — area drives the total).
     */
    public static function effectiveQuantity(Product $product, int $quantity, ?string $areaPricingMode = null): int
    {
        return self::isPerM2($product, $areaPricingMode) ? 1 : max(1, $quantity);
    }

    /**
     * Snapshot fields for order_items.
     *
     * @return array{
     *     pricing_type: string,
     *     required_area: ?float,
     *     price_includes: ?array,
     *     tree_quantity: ?int,
     *     palm_tree_quantity: ?int,
     *     price_per_tree: ?float,
     *     price_per_palm_tree: ?float
     * }
     */
    public static function orderItemSnapshot(
        Product $product,
        ?float $requiredArea,
        ?int $treeQuantity = null,
        ?int $palmTreeQuantity = null,
        ?string $areaPricingMode = null
    ): array {
        $isService = self::appliesToProduct($product);
        if ($isService) {
            $type = self::normalizeAreaPricingMode($areaPricingMode) ?? self::implicitAreaPricingMode($product);
            $includes = self::resolveConfigForProduct($product)['price_includes'];
            $tree = ServiceTreePricing::resolveForCheckout($product, $treeQuantity, $palmTreeQuantity);
        } else {
            $type = self::TYPE_FIXED;
            $includes = null;
            $tree = [
                'tree_quantity' => null,
                'palm_tree_quantity' => null,
                'price_per_tree' => 0.0,
                'price_per_palm_tree' => 0.0,
            ];
        }

        return [
            'pricing_type' => $type,
            'area_pricing_mode' => $isService ? $type : null,
            'required_area' => $type === self::TYPE_PER_M2 ? $requiredArea : null,
            'price_includes' => $includes,
            'tree_quantity' => $tree['tree_quantity'],
            'palm_tree_quantity' => $tree['palm_tree_quantity'],
            'price_per_tree' => $tree['tree_quantity'] !== null ? $tree['price_per_tree'] : null,
            'price_per_palm_tree' => $tree['palm_tree_quantity'] !== null ? $tree['price_per_palm_tree'] : null,
        ];
    }

    /**
     * Fields to expose on cart / order line APIs.
     *
     * @return array<string, mixed>
     */
    public static function lineApiFields(
        Product $product,
        float $unitPrice,
        int $quantity,
        ?float $requiredArea,
        ?int $treeQuantity = null,
        ?int $palmTreeQuantity = null,
        ?string $areaPricingMode = null
    ): array {
        $isPerM2 = self::isPerM2($product, $areaPricingMode);
        $area = $isPerM2 ? $requiredArea : null;
        $rate = $isPerM2 ? self::perM2Rate($product) : null;
        $lineTotal = self::lineTotal($product, $unitPrice, $quantity, $requiredArea, $treeQuantity, $palmTreeQuantity, null, null, $areaPricingMode);
        $includes = $isPerM2 || self::appliesToProduct($product)
            ? (is_array($product->price_includes) ? array_merge(self::emptyIncludes(), $product->price_includes) : self::emptyIncludes())
            : null;
        $baseTotal = $isPerM2
            ? (($requiredArea !== null && $requiredArea > 0)
                ? round($requiredArea * (float) $rate, 2)
                : round(max(1, $quantity) * (
                    (abs($unitPrice - (float) $rate) > 0.001) ? $unitPrice : round((float) $product->price, 2)
                ), 2))
            : round(max(1, $quantity) * $unitPrice, 2);
        $displayUnit = $isPerM2 && $area !== null ? (float) $rate : $unitPrice;

        return array_merge([
            'pricing_type' => $isPerM2 ? self::TYPE_PER_M2 : self::TYPE_FIXED,
            'area_pricing_mode' => self::appliesToProduct($product)
                ? (self::normalizeAreaPricingMode($areaPricingMode) ?? self::implicitAreaPricingMode($product))
                : null,
            'requires_area' => false,
            'area_optional' => $isPerM2,
            'required_area' => $area,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'price_per_m2' => $rate,
            'unit_price' => $displayUnit,
            'unit_price_label' => $isPerM2 && $area !== null
                ? self::formatMoney((float) $rate).' / m²'
                : self::formatMoney($displayUnit),
            'base_line_total' => $baseTotal,
            'base_line_total_label' => self::formatMoney($baseTotal),
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $rate,
                'unit_price_label' => self::formatMoney((float) $rate).'/m²',
                'total' => $baseTotal,
                'total_label' => self::formatMoney($baseTotal),
                'formula' => $area.' × '.$rate.' = '.$baseTotal,
            ] : null,
        ], ServiceTreePricing::lineApiFields($product, $treeQuantity, $palmTreeQuantity));
    }

    /**
     * Order-item API fields from persisted snapshot (product may have changed).
     *
     * @return array<string, mixed>
     */
    public static function orderItemApiFields(\App\Models\OrderItem $item): array
    {
        $type = self::normalizeType($item->pricing_type ?? null, true);
        $isPerM2 = $type === self::TYPE_PER_M2;
        $unitPrice = round((float) $item->price, 2);
        $area = $item->required_area !== null ? round((float) $item->required_area, 2) : null;
        $includes = is_array($item->price_includes)
            ? array_merge(self::emptyIncludes(), $item->price_includes)
            : null;
        $lineTotal = round((float) $item->subtotal, 2);
        $treeQty = isset($item->tree_quantity) ? (int) $item->tree_quantity : null;
        $palmQty = isset($item->palm_tree_quantity) ? (int) $item->palm_tree_quantity : null;
        $treeRate = isset($item->price_per_tree) ? (float) $item->price_per_tree : null;
        $palmRate = isset($item->price_per_palm_tree) ? (float) $item->price_per_palm_tree : null;
        $addon = ServiceTreePricing::addonTotal(
            $treeQty > 0 ? $treeQty : null,
            $palmQty > 0 ? $palmQty : null,
            $treeRate,
            $palmRate
        );
        $baseTotal = round(max(0, $lineTotal - $addon), 2);
        $treeFields = [
            'tree_quantity' => $treeQty > 0 ? $treeQty : null,
            'palm_tree_quantity' => $palmQty > 0 ? $palmQty : null,
            'price_per_tree' => $treeRate !== null && $treeRate > 0 ? round($treeRate, 2) : null,
            'price_per_palm_tree' => $palmRate !== null && $palmRate > 0 ? round($palmRate, 2) : null,
            'tree_palm_addon' => $addon,
        ];
        $treeFields['tree_palm_addon_label'] = self::formatMoney($treeFields['tree_palm_addon']);

        return [
            'pricing_type' => $type,
            'required_area' => $isPerM2 ? $area : null,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'unit_price' => $unitPrice,
            'unit_price_label' => $isPerM2
                ? self::formatMoney($unitPrice).' / m²'
                : self::formatMoney($unitPrice),
            'base_line_total' => $baseTotal,
            'base_line_total_label' => self::formatMoney($baseTotal),
            // Combined persisted subtotal (base + add-ons). Checkout UI expands via expandCheckoutDisplayLines.
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $unitPrice,
                'unit_price_label' => self::formatMoney($unitPrice).'/m²',
                'total' => $baseTotal,
                'total_label' => self::formatMoney($baseTotal),
                'formula' => $area.' × '.$unitPrice.' = '.$baseTotal,
            ] : null,
            ...$treeFields,
        ];
    }
}
