<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CategoryShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSettingsApiController extends Controller
{
    /**
     * GET /api/admin/settings
     * Return all app settings for the React Native Settings screen (grouped).
     */
    public function index(): JsonResponse
    {
        $system = [
            'push_notifications_enabled' => (bool) (Setting::get('push_notifications_enabled', '1') === '1'),
            'auto_assign_tasks' => (bool) (Setting::get('auto_assign_tasks', '0') === '1'),
            'maintenance_mode' => (bool) (Setting::get('maintenance_mode', '0') === '1'),
        ];

        $appConfig = [
            'theme' => Setting::get('app_theme', 'system'),
            'language' => Setting::get('app_language', 'en'),
            'region' => Setting::get('app_region', ''),
        ];

        $payment = $this->getPaymentSummary();
        $shop = $this->getShopSummary();

        $legal = [
            'privacy_policy_url' => Setting::get('privacy_policy_url', ''),
            'terms_of_service_url' => Setting::get('terms_of_service_url', ''),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'system' => $system,
                'app_config' => $appConfig,
                'payment' => $payment,
                'shop' => $shop,
                'legal' => $legal,
            ],
        ]);
    }

    /**
     * GET /api/admin/settings/shop
     * Shipping amount (0 = free) and tax % for checkout/cart order summary.
     */
    public function getShop(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->getShopSummary()]);
    }

    protected function getShopSummary(): array
    {
        $shipping = Setting::get('shop_shipping_amount');
        $tax = Setting::get('shop_tax_percent');

        return [
            'shipping_amount' => (float) ($shipping !== null && $shipping !== '' ? $shipping : config('shop.shipping_amount', 0)),
            'tax_percent' => (float) ($tax !== null && $tax !== '' ? $tax : config('shop.tax_percent', 5)),
            'currency' => config('shop.currency', 'AED'),
            'category_shipping_rates' => CategoryShippingService::allCategoryRatesForAdmin(),
            'shipping_note' => 'Set per-category delivery fees (bike for small items, car for large). Checkout adds one fee per category in the cart. Categories without a fee use global shipping_amount once per order.',
        ];
    }

    /**
     * GET /api/admin/settings/shop/category-shipping
     * List all categories with optional per-category delivery fee.
     */
    public function getCategoryShipping(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'global_shipping_amount' => $this->getShopSummary()['shipping_amount'],
                'rates' => CategoryShippingService::allCategoryRatesForAdmin(),
            ],
        ]);
    }

    /**
     * PUT /api/admin/settings/shop/category-shipping
     * Body: { "rates": [ { "category_id": 1, "shipping_amount": 25 }, { "category_id": 2, "shipping_amount": null } ] }
     */
    public function updateCategoryShipping(Request $request): JsonResponse
    {
        $request->validate([
            'rates' => 'required|array',
            'rates.*.category_id' => 'required|integer|exists:categories,id',
            'rates.*.shipping_amount' => 'nullable|numeric|min:0',
            'rates.*.shipping_cost' => 'nullable|numeric|min:0',
            'rates.*.shipping_type' => 'nullable|string|in:bike,car',
            'rates.*.delivery_type' => 'nullable|string|in:bike,car',
            'rates.*.tax_percentage' => 'nullable|numeric|min:0|max:100',
            'rates.*.delivery_type' => 'nullable|string|in:bike,car',
        ]);

        CategoryShippingService::syncAdminRates($request->input('rates', []));

        return $this->getCategoryShipping();
    }

    /**
     * PUT /api/admin/settings/shop
     * Update shipping amount (number, 0 = free) and/or tax_percent (e.g. 5 for 5%).
     */
    public function updateShop(Request $request): JsonResponse
    {
        $request->validate([
            'shipping_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($request->has('shipping_amount')) {
            Setting::set('shop_shipping_amount', (string) $request->input('shipping_amount'), 'text', 'shop');
        }
        if ($request->has('tax_percent')) {
            Setting::set('shop_tax_percent', (string) $request->input('tax_percent'), 'text', 'shop');
        }

        return $this->getShop();
    }

    /**
     * GET /api/admin/settings/system
     */
    public function getSystem(): JsonResponse
    {
        $data = [
            'push_notifications_enabled' => (bool) (Setting::get('push_notifications_enabled', '1') === '1'),
            'auto_assign_tasks' => (bool) (Setting::get('auto_assign_tasks', '0') === '1'),
            'maintenance_mode' => (bool) (Setting::get('maintenance_mode', '0') === '1'),
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * PUT /api/admin/settings/system
     * Update system toggles: push_notifications_enabled, auto_assign_tasks, maintenance_mode.
     */
    public function updateSystem(Request $request): JsonResponse
    {
        $request->validate([
            'push_notifications_enabled' => 'nullable|boolean',
            'auto_assign_tasks' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
        ]);

        if ($request->has('push_notifications_enabled')) {
            Setting::set('push_notifications_enabled', $request->boolean('push_notifications_enabled') ? '1' : '0', 'text', 'system');
        }
        if ($request->has('auto_assign_tasks')) {
            Setting::set('auto_assign_tasks', $request->boolean('auto_assign_tasks') ? '1' : '0', 'text', 'system');
        }
        if ($request->has('maintenance_mode')) {
            Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0', 'text', 'system');
        }

        return $this->getSystem();
    }

    /**
     * GET /api/admin/settings/theme
     */
    public function getTheme(): JsonResponse
    {
        $theme = Setting::get('app_theme', 'system');
        $available = ['system', 'light', 'dark'];
        return response()->json([
            'success' => true,
            'data' => [
                'current' => $theme,
                'available' => $available,
            ],
        ]);
    }

    /**
     * PUT /api/admin/settings/theme
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $request->validate(['theme' => 'required|in:system,light,dark']);
        Setting::set('app_theme', $request->theme, 'text', 'app_config');
        return $this->getTheme();
    }

    /**
     * GET /api/admin/settings/language
     */
    public function getLanguage(): JsonResponse
    {
        $language = Setting::get('app_language', 'en');
        $region = Setting::get('app_region', '');
        $available = [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'ar', 'name' => 'العربية'],
        ];
        return response()->json([
            'success' => true,
            'data' => [
                'current_language' => $language,
                'current_region' => $region,
                'available' => $available,
            ],
        ]);
    }

    /**
     * PUT /api/admin/settings/language
     */
    public function updateLanguage(Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|string|max:10',
            'region' => 'nullable|string|max:10',
        ]);
        Setting::set('app_language', $request->language, 'text', 'app_config');
        if ($request->has('region')) {
            Setting::set('app_region', $request->region ?? '', 'text', 'app_config');
        }
        return $this->getLanguage();
    }

    /**
     * GET /api/admin/settings/payment
     * Payment config for mobile (gateway and masked key indicator; never expose full secrets).
     */
    public function getPayment(): JsonResponse
    {
        $data = $this->getPaymentSummary();
        return response()->json(['success' => true, 'data' => $data]);
    }

    protected function getPaymentSummary(): array
    {
        $gateway = Setting::get('payment_gateway', '');
        $apiKey = Setting::get('payment_api_key', '');
        return [
            'payment_gateway' => $gateway,
            'api_key_set' => ! empty($apiKey),
            'api_secret_set' => ! empty(Setting::get('payment_api_secret', '')),
        ];
    }

    /**
     * PUT /api/admin/settings/payment
     * Update payment gateway and credentials (same as web admin).
     */
    public function updatePayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_gateway' => 'required|in:stripe,paymob,ccavenue,tap',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        Setting::set('payment_gateway', $request->payment_gateway, 'text', 'payment');
        Setting::set('payment_api_key', $request->api_key, 'text', 'payment');
        Setting::set('payment_api_secret', $request->api_secret, 'text', 'payment');

        return $this->getPayment();
    }

    /**
     * GET /api/admin/settings/legal?type=privacy|terms
     * Return URL or content for Privacy Policy / Terms of Service.
     */
    public function getLegal(Request $request): JsonResponse
    {
        $type = $request->input('type', 'privacy');
        if (! in_array($type, ['privacy', 'terms'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid type.'], 400);
        }

        $urlKey = $type === 'privacy' ? 'privacy_policy_url' : 'terms_of_service_url';
        $contentKey = $type === 'privacy' ? 'privacy_policy_content' : 'terms_of_service_content';

        $url = Setting::get($urlKey, '');
        $content = Setting::get($contentKey, '');

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $type,
                'url' => $url,
                'content' => $content,
            ],
        ]);
    }

    /**
     * POST /api/admin/settings/export-data
     * Trigger data export; returns job id or status. Mobile can poll or use push when ready.
     */
    public function exportData(Request $request): JsonResponse
    {
        $format = $request->input('format', 'json');
        if (! in_array($format, ['json', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid format.'], 400);
        }

        // For now return success and a placeholder; you can queue a real export job later.
        $exportId = 'export-' . uniqid();
        Setting::set('last_export_id', $exportId, 'text', 'system');
        Setting::set('last_export_at', now()->toIso8601String(), 'text', 'system');

        return response()->json([
            'success' => true,
            'message' => 'Export requested.',
            'data' => [
                'export_id' => $exportId,
                'format' => $format,
                'status' => 'pending',
            ],
        ], 202);
    }
}
