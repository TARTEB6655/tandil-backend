<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;

/**
 * Area-based (per m²) pricing for service products only.
 * Global Admin Setting (like Instant Order Fee) — applies to ALL Services-channel products.
 * Category/shop simple products are unchanged (Instant Order Fee still applies there).
 */
final class ServiceAreaPricing
{
    public const TYPE_FIXED = 'fixed';

    public const TYPE_PER_M2 = 'per_m2';

    public const SETTING_PRICING_TYPE = 'service_pricing_type';

    public const SETTING_PRICE = 'service_pricing_price';

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
     * Whether global Fixed / per-m² settings apply to this catalog line.
     * Matches service shop listing: type=service OR linked via product_service pivot
     * (same set as GET /api/services/.../products). Pure shop SKUs without a service link are excluded.
     */
    public static function appliesToProduct(Product $product): bool
    {
        $type = strtolower(trim((string) ($product->type ?? '')));
        if ($type === 'service') {
            return true;
        }

        if (! $product->relationLoaded('services')) {
            $product->loadMissing('services');
        }

        return $product->services->isNotEmpty();
    }

    public static function isPerM2(Product $product): bool
    {
        if (! self::appliesToProduct($product)) {
            return false;
        }

        // Global admin setting wins for all services.
        return self::globalConfig()['pricing_type'] === self::TYPE_PER_M2;
    }

    /**
     * Effective unit price for a catalog product.
     * Per m² services: global admin rate (× area at line total).
     * Fixed services: product base (catalog) price + global Fixed amount.
     */
    public static function effectiveUnitPrice(Product $product, float $fallbackProductPrice): float
    {
        if (! self::appliesToProduct($product)) {
            return round($fallbackProductPrice, 2);
        }

        $config = self::globalConfig();
        if ($config['pricing_type'] === self::TYPE_PER_M2) {
            return round((float) $config['price'], 2);
        }

        // Fixed: base product price + global Fixed setting (e.g. 100 + 800 = 900).
        return round($fallbackProductPrice + (float) $config['price'], 2);
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
     * Customer / admin product payload fields for pricing UI.
     *
     * Store listing uses `price` = catalog product price (never replaced by global rate).
     * Per m² checkout uses `price_per_m2` from global admin setting.
     *
     * @return array<string, mixed>
     */
    public static function productApiFields(Product $product): array
    {
        $isService = self::appliesToProduct($product);
        $catalogPrice = round((float) $product->price, 2);

        if (! $isService) {
            return [
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
            ];
        }

        $config = self::globalConfig();
        $type = $config['pricing_type'];
        $includes = $config['price_includes'];
        $rate = $config['price'];

        if ($type === self::TYPE_PER_M2) {
            return [
                'pricing_type' => self::TYPE_PER_M2,
                // Store/list card: each product's own catalog price (never the global m² rate).
                'price' => $catalogPrice,
                'catalog_price' => $catalogPrice,
                'price_label' => self::formatMoney($catalogPrice),
                'list_price_label' => self::formatMoney($catalogPrice),
                // Checkout: global admin rate × required area.
                'price_per_m2' => $rate,
                'unit_rate' => $rate,
                'unit_rate_label' => self::formatMoney($rate).' / m²',
                'checkout_rate_label' => self::formatMoney($rate).' / m²',
                'currency' => 'AED',
                'price_unit' => 'm²',
                'requires_area' => true,
                'price_includes' => $includes,
                'price_includes_labels' => self::includeLabels($includes),
                'customer_preview' => [
                    'price_display' => 'Listed: '.self::formatMoney($catalogPrice),
                    'checkout_rate_display' => 'Checkout rate: '.self::formatMoney($rate).' / m²',
                    'note' => 'On the store list/detail card show price + price_label (catalog). On checkout use price_per_m2 × required_area. Do NOT show the global rate as every service list price.',
                    'example' => [
                        'area' => 100,
                        'price_per_m2' => $rate,
                        'total' => round(100 * $rate, 2),
                        'total_label' => self::formatMoney(round(100 * $rate, 2)),
                    ],
                ],
            ];
        }

        // Fixed: customer pays product base + global Fixed amount (e.g. 100 + 800 = 900).
        $fixedAddon = round((float) $rate, 2);
        $total = round($catalogPrice + $fixedAddon, 2);

        return [
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
            'customer_preview' => [
                'price_display' => 'Price: '.self::formatMoney($total),
                'breakdown' => [
                    'base' => $catalogPrice,
                    'base_label' => self::formatMoney($catalogPrice),
                    'fixed_addon' => $fixedAddon,
                    'fixed_addon_label' => self::formatMoney($fixedAddon),
                    'total' => $total,
                    'total_label' => self::formatMoney($total),
                    'formula' => $catalogPrice.' + '.$fixedAddon.' = '.$total,
                ],
                'note' => 'Fixed mode: product base price + global Fixed price. No area field.',
            ],
        ];
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

        $type = $fields['pricing_type'];
        // Admin input shows the global setting amount (Fixed addon / per-m² rate), not the customer total.
        $price = $isService
            ? round((float) self::globalConfig()['price'], 2)
            : round((float) ($product->price ?? 0), 2);

        return [
            'product_id' => (int) $product->id,
            'product_name' => (string) $product->name,
            'type' => $product->type ?? 'product',
            'is_service' => $isService,
            'settings_available' => $isService,
            'pricing_type' => $type,
            'pricing_type_options' => [
                [
                    'value' => self::TYPE_FIXED,
                    'label' => 'Fixed Price',
                    'description' => 'This amount is added to each service product base price (e.g. base 100 + Fixed 800 = 900).',
                    'selected' => $type === self::TYPE_FIXED,
                ],
                [
                    'value' => self::TYPE_PER_M2,
                    'label' => 'Price per Square Meter (m²)',
                    'description' => 'Enter the unit price. Customers will enter the required area manually.',
                    'selected' => $type === self::TYPE_PER_M2,
                ],
            ],
            'price' => $price,
            'price_per_m2' => $type === self::TYPE_PER_M2 ? $price : null,
            'currency' => 'AED',
            'price_unit' => $type === self::TYPE_PER_M2 ? 'm²' : null,
            'price_input_label' => $type === self::TYPE_PER_M2 ? 'Price per m²' : 'Fixed price (added to product base)',
            'price_input_suffix' => $type === self::TYPE_PER_M2 ? 'AED / m²' : 'AED',
            'price_label' => $type === self::TYPE_FIXED
                ? self::formatMoney($price).' addon'
                : ($fields['price_label'] ?? self::formatMoney($price)),
            'requires_area' => (bool) $fields['requires_area'],
            'price_includes' => $includes,
            'price_includes_options' => $includeOptions,
            'price_includes_labels' => self::includeLabels($includes),
            'customer_preview' => $type === self::TYPE_FIXED
                ? [
                    'price_display' => 'Example: base AED 100 + Fixed '.self::formatMoney($price).' = '.self::formatMoney(round(100 + $price, 2)),
                    'note' => 'On the customer app: show base + Fixed total. No area field is required for checkout.',
                ]
                : ($fields['customer_preview'] ?? null),
            'example_calculation' => $type === self::TYPE_PER_M2 ? [
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
            ],
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

    public static function validateAreaMessage(Product $product, mixed $areaRaw): ?string
    {
        if (! self::isPerM2($product)) {
            return null;
        }
        $area = self::normalizeArea($areaRaw);
        if ($area === null) {
            return 'Required Area (m²) is required and must be a positive number (e.g. 50, 100, 137.5). Send it as required_area (or area / requiredArea).';
        }

        return null;
    }

    /**
     * Buy Now / payment-intent often omit required_area even after the user entered it
     * on Product Details (and/or it was saved on a cart line). Hydrate from aliases first,
     * then from the user's cart row for that product.
     */
    public static function hydrateMissingAreaOntoRequest(
        \Illuminate\Http\Request $request,
        int $userId,
        ?Product $product = null
    ): void {
        $resolved = self::resolveAreaFromRequest($request, false);
        if (self::normalizeArea($resolved) !== null) {
            if (! $request->filled('required_area')) {
                $request->merge(['required_area' => self::normalizeArea($resolved)]);
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
        if ($normalized !== null) {
            $request->merge(['required_area' => $normalized]);
        }
    }

    /**
     * Line total for a cart/order line.
     * Fixed: quantity × unitPrice. Per m²: required_area × unitPrice (quantity ignored for money).
     */
    public static function lineTotal(Product $product, float $unitPrice, int $quantity, ?float $requiredArea): float
    {
        if (self::isPerM2($product)) {
            $area = $requiredArea !== null && $requiredArea > 0 ? $requiredArea : 0.0;

            return round($area * $unitPrice, 2);
        }

        return round(max(1, $quantity) * $unitPrice, 2);
    }

    /**
     * Quantity stored on cart/order for per-m² lines (always 1 — area drives the total).
     */
    public static function effectiveQuantity(Product $product, int $quantity): int
    {
        return self::isPerM2($product) ? 1 : max(1, $quantity);
    }

    /**
     * Snapshot fields for order_items.
     *
     * @return array{pricing_type: string, required_area: ?float, price_includes: ?array}
     */
    public static function orderItemSnapshot(Product $product, ?float $requiredArea): array
    {
        $isService = self::appliesToProduct($product);
        if ($isService) {
            $config = self::globalConfig();
            $type = $config['pricing_type'];
            $includes = $config['price_includes'];
        } else {
            $type = self::TYPE_FIXED;
            $includes = null;
        }

        return [
            'pricing_type' => $type,
            'required_area' => $type === self::TYPE_PER_M2 ? $requiredArea : null,
            'price_includes' => $includes,
        ];
    }

    /**
     * Fields to expose on cart / order line APIs.
     *
     * @return array<string, mixed>
     */
    public static function lineApiFields(Product $product, float $unitPrice, int $quantity, ?float $requiredArea): array
    {
        $isPerM2 = self::isPerM2($product);
        $area = $isPerM2 ? $requiredArea : null;
        $lineTotal = self::lineTotal($product, $unitPrice, $quantity, $requiredArea);
        $includes = $isPerM2 || self::appliesToProduct($product)
            ? (is_array($product->price_includes) ? array_merge(self::emptyIncludes(), $product->price_includes) : self::emptyIncludes())
            : null;

        return [
            'pricing_type' => $isPerM2 ? self::TYPE_PER_M2 : self::TYPE_FIXED,
            'requires_area' => $isPerM2,
            'required_area' => $area,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'unit_price' => $unitPrice,
            'unit_price_label' => $isPerM2
                ? self::formatMoney($unitPrice).' / m²'
                : self::formatMoney($unitPrice),
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $unitPrice,
                'unit_price_label' => self::formatMoney($unitPrice).'/m²',
                'total' => $lineTotal,
                'total_label' => self::formatMoney($lineTotal),
                'formula' => $area.' × '.$unitPrice.' = '.$lineTotal,
            ] : null,
        ];
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

        return [
            'pricing_type' => $type,
            'required_area' => $isPerM2 ? $area : null,
            'area_unit' => $isPerM2 ? 'm²' : null,
            'unit_price' => $unitPrice,
            'unit_price_label' => $isPerM2
                ? self::formatMoney($unitPrice).' / m²'
                : self::formatMoney($unitPrice),
            'line_total' => $lineTotal,
            'line_total_label' => self::formatMoney($lineTotal),
            'price_includes' => $includes,
            'price_includes_labels' => self::includeLabels($includes),
            'pricing_breakdown' => $isPerM2 && $area !== null ? [
                'area' => $area,
                'area_label' => rtrim(rtrim(number_format($area, 2, '.', ''), '0'), '.').' m²',
                'unit_price' => $unitPrice,
                'unit_price_label' => self::formatMoney($unitPrice).'/m²',
                'total' => $lineTotal,
                'total_label' => self::formatMoney($lineTotal),
                'formula' => $area.' × '.$unitPrice.' = '.$lineTotal,
            ] : null,
        ];
    }
}
