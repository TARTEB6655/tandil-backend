<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Per-product area pricing for vendor catalog SKUs (replaces vendor-wide screen for mobile list → edit flow).
 */
final class ProductAreaPricing
{
    public const SETTING_CONFIGURED = 'area_pricing_configured';

    public const SETTING_SHOW_ON_CLIENT = 'area_pricing_show_on_client';

    public const SETTING_ENABLE_FIXED = 'area_pricing_enable_fixed';

    public const SETTING_ENABLE_PER_M2 = 'area_pricing_enable_per_m2';

    public const SETTING_FIXED_AMOUNT = 'area_pricing_fixed_amount';

    public const SETTING_PER_M2_RATE = 'area_pricing_per_m2_rate';

    public const SETTING_REQUIRE_AREA = 'area_pricing_require_area';

    public const SETTING_INCLUDES = 'area_pricing_includes';

    public static function settingKey(int $productId, string $suffix): string
    {
        return 'product_'.$productId.'_'.$suffix;
    }

    /**
     * Vendor service SKUs only (type=service) — not category products or type=product lines.
     *
     * @return Builder<Product>
     */
    public static function vendorServiceProductsQuery(int $vendorId): Builder
    {
        return Product::query()
            ->where('vendor_id', $vendorId)
            ->where('type', 'service')
            ->where('status', 'active')
            ->orderBy('name');
    }

    public static function resolveVendorServiceProduct(Vendor $vendor, int $productId): Product
    {
        $product = Product::query()
            ->where('id', $productId)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->where('type', 'service')
            ->first();

        if ($product === null) {
            abort(404, 'Service not found for this vendor.');
        }

        return $product;
    }

    public static function isConfigured(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        return filter_var(
            Setting::get(self::settingKey($productId, self::SETTING_CONFIGURED), '0'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @return array{
     *     configured: bool,
     *     show_on_client_products_services: bool,
     *     enable_fixed_price: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float,
     *     require_area_before_checkout: bool,
     *     price_includes: array<string, bool>,
     *     area_pricing_multiselect: bool,
     *     pricing_type: string
     * }
     */
    public static function configExpanded(int $productId): array
    {
        if (! self::isConfigured($productId)) {
            return self::unconfiguredAreaPricingDefaults();
        }

        $includesRaw = Setting::get(self::settingKey($productId, self::SETTING_INCLUDES), null);
        $includes = ServiceAreaPricing::normalizeIncludes($includesRaw, true) ?? ServiceAreaPricing::emptyIncludes();

        $enableFixed = filter_var(
            Setting::get(self::settingKey($productId, self::SETTING_ENABLE_FIXED), '0'),
            FILTER_VALIDATE_BOOLEAN
        );
        $enablePerM2 = filter_var(
            Setting::get(self::settingKey($productId, self::SETTING_ENABLE_PER_M2), '0'),
            FILTER_VALIDATE_BOOLEAN
        );
        $fixedPrice = max(0, round((float) Setting::get(self::settingKey($productId, self::SETTING_FIXED_AMOUNT), '0'), 2));
        $perM2Price = max(0, round((float) Setting::get(self::settingKey($productId, self::SETTING_PER_M2_RATE), '0'), 2));
        $show = filter_var(
            Setting::get(self::settingKey($productId, self::SETTING_SHOW_ON_CLIENT), '0'),
            FILTER_VALIDATE_BOOLEAN
        );
        $requireArea = filter_var(
            Setting::get(self::settingKey($productId, self::SETTING_REQUIRE_AREA), '0'),
            FILTER_VALIDATE_BOOLEAN
        );

        $multiselect = $enableFixed && $enablePerM2;
        $pricingType = $multiselect
            ? 'multi'
            : ($enablePerM2 ? ServiceAreaPricing::TYPE_PER_M2 : ServiceAreaPricing::TYPE_FIXED);

        return [
            'configured' => true,
            'show_on_client_products_services' => $show,
            'enable_fixed_price' => $enableFixed,
            'enable_per_m2' => $enablePerM2,
            'fixed_price' => $fixedPrice,
            'per_m2_price' => $perM2Price,
            'require_area_before_checkout' => $requireArea,
            'price_includes' => $includes,
            'area_pricing_multiselect' => $multiselect,
            'pricing_type' => $pricingType,
        ];
    }

    public static function showOnClient(Product $product): bool
    {
        if ((int) ($product->vendor_id ?? 0) <= 0 || ! self::isConfigured((int) $product->id)) {
            return false;
        }

        return (bool) self::configExpanded((int) $product->id)['show_on_client_products_services'];
    }

    public static function requiresAreaBeforeCheckout(Product $product): bool
    {
        if (! self::isConfigured((int) $product->id)) {
            return false;
        }

        $config = self::configExpanded((int) $product->id);

        return $config['require_area_before_checkout'] && $config['enable_per_m2'];
    }

    /**
     * @return array{fixed: bool, per_m2: bool, fixed_amount: float, per_m2_rate: float}
     */
    public static function modesForProduct(Product $product): array
    {
        $config = self::configExpanded((int) $product->id);

        return [
            'fixed' => (bool) $config['enable_fixed_price'],
            'per_m2' => (bool) $config['enable_per_m2'],
            'fixed_amount' => (float) $config['fixed_price'],
            'per_m2_rate' => (float) $config['per_m2_price'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * List card for area-pricing APIs — services only (no category / product-type rows).
     *
     * @return array<string, mixed>
     */
    public static function serviceAreaPricingListItem(Product $product, ?int $adminVendorId = null): array
    {
        $catalog = round((float) $product->price, 2);
        $config = self::configExpanded((int) $product->id);
        $perM2 = $config['enable_per_m2'] ? round((float) $config['per_m2_price'], 2) : null;

        $serviceId = (int) $product->id;
        $detailEndpoint = $adminVendorId !== null
            ? '/api/admin/vendors/'.$adminVendorId.'/services/'.$serviceId.'/area-pricing'
            : '/api/vendor/services/'.$serviceId.'/area-pricing';

        return [
            'id' => $serviceId,
            'service_id' => $serviceId,
            'name' => (string) $product->name,
            'description' => $product->description,
            'catalog_price' => $catalog,
            'catalog_price_label' => 'Catalog: '.ServiceAreaPricing::formatMoney($catalog),
            'area_pricing_configured' => (bool) $config['configured'],
            'price_per_m2' => $perM2,
            'price_per_m2_label' => $perM2 !== null && $perM2 > 0
                ? ServiceAreaPricing::formatMoney($perM2).' / m²'
                : null,
            'show_on_client_products_services' => (bool) $config['show_on_client_products_services'],
            'detail_endpoint' => $detailEndpoint,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Fresh per-service area pricing form — no vendor-wide 08b or platform catalog includes.
     *
     * @return array{
     *     configured: bool,
     *     show_on_client_products_services: bool,
     *     enable_fixed_price: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float,
     *     require_area_before_checkout: bool,
     *     price_includes: array<string, bool>,
     *     area_pricing_multiselect: bool,
     *     pricing_type: string
     * }
     */
    public static function unconfiguredAreaPricingDefaults(): array
    {
        return [
            'configured' => false,
            'show_on_client_products_services' => false,
            'enable_fixed_price' => false,
            'enable_per_m2' => false,
            'fixed_price' => 0.0,
            'per_m2_price' => 0.0,
            'require_area_before_checkout' => false,
            'price_includes' => ServiceAreaPricing::emptyIncludes(),
            'area_pricing_multiselect' => false,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
        ];
    }

    public static function vendorManagePayload(Product $product): array
    {
        $config = self::configExpanded((int) $product->id);
        if (! $config['configured']) {
            $config = self::unconfiguredAreaPricingDefaults();
        }

        $catalog = round((float) $product->price, 2);
        $perM2 = round((float) $config['per_m2_price'], 2);
        $fixed = round((float) $config['fixed_price'], 2);

        $includeOptions = [];
        foreach (ServiceAreaPricing::INCLUDE_KEYS as $key) {
            $includeOptions[] = [
                'key' => $key,
                'label' => ServiceAreaPricing::INCLUDE_LABELS[$key],
                'selected' => (bool) ($config['price_includes'][$key] ?? false),
            ];
        }

        $areaSettingsBlock = [
            'area_pricing_multiselect' => (bool) $config['area_pricing_multiselect'],
            'enable_fixed_price' => (bool) $config['enable_fixed_price'],
            'enable_per_m2' => (bool) $config['enable_per_m2'],
            'fixed_price' => $fixed,
            'per_m2_price' => $perM2,
        ];

        return [
            'screen_title' => 'Area pricing for this service',
            'screen_description' => 'Set this service’s m² rate (and optional fixed price). Shown on the customer product detail when “Show on client” is on.',
            'service_id' => (int) $product->id,
            'id' => (int) $product->id,
            'service_name' => (string) $product->name,
            'product_name' => (string) $product->name,
            'catalog_price' => $catalog,
            'catalog_price_label' => ServiceAreaPricing::formatMoney($catalog),
            'area_pricing_configured' => (bool) $config['configured'],
            'show_on_client_products_services' => (bool) $config['show_on_client_products_services'],
            'enable_per_m2' => (bool) $config['enable_per_m2'],
            'enable_fixed_price' => (bool) $config['enable_fixed_price'],
            'require_area_before_checkout' => (bool) $config['require_area_before_checkout'],
            'per_m2_price' => $perM2,
            'price_per_m2' => $perM2,
            'fixed_price' => $fixed,
            'pricing_type' => $config['pricing_type'],
            'area_pricing_multiselect' => (bool) $config['area_pricing_multiselect'],
            'price_includes' => $config['price_includes'],
            'price_includes_options' => $includeOptions,
            'show_on_client' => (bool) $config['show_on_client_products_services'],
            'show_area_pricing_on_client' => (bool) $config['show_on_client_products_services'],
            'area_pricing_settings' => ServiceAreaPricing::vendorAreaPricingSettingsBlock($areaSettingsBlock),
            'example_calculation' => $config['enable_per_m2'] ? [
                'title' => 'Example',
                'area' => 100,
                'area_label' => '100 m²',
                'price_per_m2' => $perM2,
                'price_per_m2_label' => ServiceAreaPricing::formatMoney($perM2).' / m²',
                'total' => round(100 * $perM2, 2),
                'total_label' => ServiceAreaPricing::formatMoney(round(100 * $perM2, 2)),
                'formula' => '100 m² × '.ServiceAreaPricing::formatMoney($perM2).' = '.ServiceAreaPricing::formatMoney(round(100 * $perM2, 2)),
            ] : null,
            'save_hint' => 'Per-service m² pricing applies on the customer app when “Show on client” is on.',
            'save_endpoint' => '/api/vendor/services/'.$product->id.'/area-pricing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminManagePayload(Product $product, Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'user']);
        $profile = $vendor->profile;

        return array_merge(self::vendorManagePayload($product), [
            'vendor_id' => $vendor->id,
            'vendor' => [
                'vendor_id' => $vendor->id,
                'company_name' => $profile?->business_name,
                'vendor_name' => $profile?->owner_name,
                'email' => $profile?->email ?? $vendor->user?->email,
            ],
            'save_endpoint' => '/api/admin/vendors/'.$vendor->id.'/services/'.$product->id.'/area-pricing',
        ]);
    }

    public static function hydrateSaveRequest(Request $request, Product $product): void
    {
        ServiceAreaPricing::mergeVendorServicePricingRequestAliases($request);

        foreach ([
            'enable_price_per_m2' => 'enable_per_m2',
            'enablePricePerM2' => 'enable_per_m2',
            'enable_area_pricing' => 'enable_per_m2',
        ] as $from => $to) {
            if ($request->has($from) && ! $request->has($to)) {
                $request->merge([$to => $request->input($from)]);
            }
        }

        $existing = self::configExpanded((int) $product->id);

        foreach (['enable_fixed_price', 'enable_per_m2', 'require_area_before_checkout'] as $key) {
            if ($request->has($key)) {
                $request->merge([
                    $key => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        foreach (['show_on_client_products_services', 'show_on_client', 'show_area_pricing_on_client'] as $key) {
            if ($request->has($key) && ! $request->has('show_on_client_products_services')) {
                $request->merge([
                    'show_on_client_products_services' => filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN),
                ]);
                break;
            }
        }

        $enableFixed = $request->has('enable_fixed_price')
            ? (bool) $request->boolean('enable_fixed_price')
            : (bool) $existing['enable_fixed_price'];
        $enablePerM2 = $request->has('enable_per_m2')
            ? (bool) $request->boolean('enable_per_m2')
            : (bool) $existing['enable_per_m2'];

        if ($request->has('pricing_type') && ! $request->has('enable_fixed_price') && ! $request->has('enable_per_m2')) {
            $type = ServiceAreaPricing::normalizeType((string) $request->input('pricing_type'), true);
            $enableFixed = $type === ServiceAreaPricing::TYPE_FIXED;
            $enablePerM2 = $type === ServiceAreaPricing::TYPE_PER_M2;
        }

        $input = $request->all();
        self::inferEnableFlagsFromAmounts($input, $enableFixed, $enablePerM2);

        $request->merge([
            'enable_fixed_price' => $enableFixed,
            'enable_per_m2' => $enablePerM2,
            'fixed_price' => self::resolveFixedFromInput($input, $existing, $enableFixed),
            'per_m2_price' => self::resolvePerM2FromInput($input, $existing, $enablePerM2),
        ]);
    }

    /**
     * Mobile often sends m² rate without toggling enable_per_m2 — infer from amounts.
     *
     * @param  array<string, mixed>  $input
     */
    private static function inferEnableFlagsFromAmounts(array $input, bool &$enableFixed, bool &$enablePerM2): void
    {
        $rawPerM2 = self::peekPerM2FromInput($input);
        $rawFixed = self::peekFixedFromInput($input);

        if (! $enablePerM2 && $rawPerM2 > 0) {
            $enablePerM2 = true;
        }
        if (! $enableFixed && $rawFixed > 0) {
            $enableFixed = true;
        }

        if (array_key_exists('pricing_type', $input) && $input['pricing_type'] !== '' && $input['pricing_type'] !== null) {
            $type = ServiceAreaPricing::normalizeType((string) $input['pricing_type'], true);
            if ($type === ServiceAreaPricing::TYPE_PER_M2 && $rawPerM2 > 0) {
                $enablePerM2 = true;
            }
            if ($type === ServiceAreaPricing::TYPE_FIXED && $rawFixed > 0) {
                $enableFixed = true;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function peekPerM2FromInput(array $input): float
    {
        foreach (['per_m2_price', 'price_per_m2', 'pricePerM2'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '' && $input[$key] !== null) {
                return max(0, round((float) $input[$key], 2));
            }
        }
        if (array_key_exists('price', $input) && ($input['pricing_type'] ?? '') !== ServiceAreaPricing::TYPE_FIXED) {
            $type = ServiceAreaPricing::normalizeType((string) ($input['pricing_type'] ?? ServiceAreaPricing::TYPE_PER_M2), true);
            if ($type === ServiceAreaPricing::TYPE_PER_M2) {
                return max(0, round((float) $input['price'], 2));
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function peekFixedFromInput(array $input): float
    {
        if (array_key_exists('fixed_price', $input) && $input['fixed_price'] !== '' && $input['fixed_price'] !== null) {
            return max(0, round((float) $input['fixed_price'], 2));
        }
        if (array_key_exists('price', $input) && ($input['pricing_type'] ?? '') === ServiceAreaPricing::TYPE_FIXED) {
            return max(0, round((float) $input['price'], 2));
        }

        return 0.0;
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    private static function resolveFixedFromInput(array $input, array $existing, bool $enableFixed): float
    {
        if (! $enableFixed) {
            return 0.0;
        }
        if (array_key_exists('fixed_price', $input) && $input['fixed_price'] !== '' && $input['fixed_price'] !== null) {
            return max(0, round((float) $input['fixed_price'], 2));
        }
        if (array_key_exists('price', $input) && ($input['pricing_type'] ?? '') === ServiceAreaPricing::TYPE_FIXED) {
            return max(0, round((float) $input['price'], 2));
        }

        return max(0, round((float) ($existing['fixed_price'] ?? 0), 2));
    }

    /**
     * @param  array<string, mixed>  $existing
     */
    private static function resolvePerM2FromInput(array $input, array $existing, bool $enablePerM2): float
    {
        if (! $enablePerM2) {
            return 0.0;
        }
        foreach (['per_m2_price', 'price_per_m2', 'pricePerM2'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== '' && $input[$key] !== null) {
                return max(0, round((float) $input[$key], 2));
            }
        }
        if (array_key_exists('price', $input)) {
            $type = ServiceAreaPricing::normalizeType((string) ($input['pricing_type'] ?? ServiceAreaPricing::TYPE_PER_M2), true);
            if ($type === ServiceAreaPricing::TYPE_PER_M2) {
                return max(0, round((float) $input['price'], 2));
            }
        }

        return max(0, round((float) ($existing['per_m2_price'] ?? 0), 2));
    }

    /**
     * @return array{
     *     enable_fixed: bool,
     *     enable_per_m2: bool,
     *     fixed_price: float,
     *     per_m2_price: float,
     *     show_on_client_products_services: bool,
     *     require_area_before_checkout: bool
     * }
     */
    public static function parseSaveInput(array $input, Product $product): array
    {
        $existing = self::configExpanded((int) $product->id);
        $show = array_key_exists('show_on_client_products_services', $input)
            ? filter_var($input['show_on_client_products_services'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $existing['show_on_client_products_services'];

        $requireArea = array_key_exists('require_area_before_checkout', $input)
            ? filter_var($input['require_area_before_checkout'], FILTER_VALIDATE_BOOLEAN)
            : (bool) $existing['require_area_before_checkout'];

        return [
            'enable_fixed' => (bool) ($input['enable_fixed_price'] ?? $existing['enable_fixed_price']),
            'enable_per_m2' => (bool) ($input['enable_per_m2'] ?? $existing['enable_per_m2']),
            'fixed_price' => (float) ($input['fixed_price'] ?? $existing['fixed_price']),
            'per_m2_price' => (float) ($input['per_m2_price'] ?? $existing['per_m2_price']),
            'show_on_client_products_services' => $show,
            'require_area_before_checkout' => $requireArea,
        ];
    }

    public static function save(
        Product $product,
        bool $enableFixed,
        bool $enablePerM2,
        float $fixedPrice,
        float $perM2Price,
        ?array $includes,
        bool $showOnClient,
        bool $requireAreaBeforeCheckout
    ): void {
        $productId = (int) $product->id;
        if ($productId <= 0) {
            throw ValidationException::withMessages(['product_id' => ['Invalid product.']]);
        }

        if ($showOnClient && ! $enableFixed && ! $enablePerM2) {
            throw ValidationException::withMessages([
                'enable_per_m2' => ['Enable at least Price per m² or Fixed price when showing on the client app.'],
            ]);
        }

        $fixedPrice = max(0, round($fixedPrice, 2));
        $perM2Price = max(0, round($perM2Price, 2));
        $includes = ServiceAreaPricing::normalizeIncludes($includes, true) ?? ServiceAreaPricing::emptyIncludes();

        Setting::set(self::settingKey($productId, self::SETTING_CONFIGURED), '1', 'boolean', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_SHOW_ON_CLIENT), $showOnClient ? '1' : '0', 'boolean', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_ENABLE_FIXED), $enableFixed ? '1' : '0', 'boolean', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_ENABLE_PER_M2), $enablePerM2 ? '1' : '0', 'boolean', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_FIXED_AMOUNT), (string) $fixedPrice, 'text', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_PER_M2_RATE), (string) $perM2Price, 'text', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_REQUIRE_AREA), $requireAreaBeforeCheckout ? '1' : '0', 'boolean', 'product_pricing');
        Setting::set(self::settingKey($productId, self::SETTING_INCLUDES), json_encode($includes), 'json', 'product_pricing');

        $multiselect = $enableFixed && $enablePerM2;
        $pricingType = $multiselect
            ? 'multi'
            : ($enablePerM2 ? ServiceAreaPricing::TYPE_PER_M2 : ServiceAreaPricing::TYPE_FIXED);

        $product->pricing_type = $pricingType;
        $product->price_includes = $includes;
        $product->save();
    }
}
