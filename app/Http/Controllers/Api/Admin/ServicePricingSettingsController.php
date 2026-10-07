<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Service;
use App\Support\ServiceAreaPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin "Product Settings" UI — stored on the **Service** (e.g. Interlock).
 * Applies to all linked products with type=service.
 * Shop/category products are unchanged (instant order fee still applies there).
 *
 * Form-data: pricing_type, price, price_includes[materials]=1, ...
 */
class ServicePricingSettingsController extends Controller
{
    /**
     * GET /api/admin/services/{id}/settings
     */
    public function show(int $id): JsonResponse
    {
        $service = Service::withCount('products')->find($id);
        if ($service === null) {
            return ApiResponse::error('Service not found.', 404);
        }

        return ApiResponse::success(
            'Service pricing settings retrieved.',
            $this->settingsPayload($service)
        );
    }

    /**
     * PUT|POST /api/admin/services/{id}/settings (form-data recommended)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::find($id);
        if ($service === null) {
            return ApiResponse::error('Service not found.', 404);
        }

        $this->normalizeFormData($request);

        $validated = $request->validate([
            'pricing_type' => 'required|in:fixed,per_m2',
            'price' => 'required|numeric|min:0',
            'price_includes' => 'nullable',
            'price_includes.materials' => 'nullable|boolean',
            'price_includes.installation' => 'nullable|boolean',
            'price_includes.labor' => 'nullable|boolean',
            'price_includes.transportation' => 'nullable|boolean',
            'price_includes.delivery' => 'nullable|boolean',
        ], [
            'pricing_type.required' => 'Select Fixed Price or Price per Square Meter (m²).',
            'pricing_type.in' => 'Pricing type must be fixed or per_m2.',
            'price.required' => 'Enter the price amount.',
        ]);

        $pricingType = ServiceAreaPricing::normalizeType($validated['pricing_type'], true);
        if (! in_array($pricingType, [ServiceAreaPricing::TYPE_FIXED, ServiceAreaPricing::TYPE_PER_M2], true)) {
            throw ValidationException::withMessages([
                'pricing_type' => ['Pricing type must be fixed or per_m2.'],
            ]);
        }

        $service->pricing_type = $pricingType;
        $service->price = round((float) $validated['price'], 2);
        $service->price_includes = ServiceAreaPricing::normalizeIncludes(
            $request->input('price_includes'),
            true
        );
        $service->save();

        $synced = $this->syncLinkedServiceProducts($service);

        $payload = $this->settingsPayload($service->fresh()->loadCount('products'));
        $payload['synced_products_count'] = $synced;

        return ApiResponse::success(
            'Service pricing settings updated. Applied to linked service products.',
            $payload
        );
    }

    /**
     * Push service pricing metadata onto linked type=service products.
     * Never overwrite catalog `price` — each product keeps its own list price.
     */
    private function syncLinkedServiceProducts(Service $service): int
    {
        $count = 0;
        $service->products()
            ->where('type', 'service')
            ->orderBy('products.id')
            ->chunkById(100, function ($products) use ($service, &$count) {
                foreach ($products as $product) {
                    /** @var Product $product */
                    $product->pricing_type = $service->pricing_type ?? ServiceAreaPricing::TYPE_FIXED;
                    $product->price_includes = is_array($service->price_includes)
                        ? $service->price_includes
                        : ServiceAreaPricing::emptyIncludes();
                    $product->save();
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(Service $service): array
    {
        // Reuse the same UI-shaped payload as Product Settings, keyed for a service.
        $shim = new Product([
            'name' => $service->name,
            'type' => 'service',
            'price' => $service->price ?? 0,
            'pricing_type' => $service->pricing_type ?? ServiceAreaPricing::TYPE_FIXED,
            'price_includes' => $service->price_includes,
        ]);
        $base = ServiceAreaPricing::productSettingsApi($shim);

        return array_merge($base, [
            'service_id' => (int) $service->id,
            'service_name' => (string) $service->name,
            'product_id' => null,
            'product_name' => null,
            'applies_to' => 'All linked products with type=service under this service',
            'products_count' => (int) ($service->products_count ?? $service->products()->count()),
            'note' => 'Shop/category products are unchanged — Instant Order Fee still applies there.',
        ]);
    }

    private function normalizeFormData(Request $request): void
    {
        if ($request->has('price_includes') && is_string($request->input('price_includes'))) {
            $decoded = json_decode($request->input('price_includes'), true);
            if (is_array($decoded)) {
                $request->merge(['price_includes' => $decoded]);
            }
        }

        $includes = is_array($request->input('price_includes'))
            ? $request->input('price_includes')
            : [];

        foreach (ServiceAreaPricing::INCLUDE_KEYS as $key) {
            foreach (["price_includes[{$key}]", "price_includes_{$key}", "includes_{$key}", $key] as $flatKey) {
                if ($request->has($flatKey) && ! array_key_exists($key, $includes)) {
                    $includes[$key] = $request->input($flatKey);
                }
            }
        }

        if ($includes !== []) {
            $request->merge([
                'price_includes' => ServiceAreaPricing::normalizeIncludes($includes, true),
            ]);
        } elseif (is_array($request->input('price_includes'))) {
            $request->merge([
                'price_includes' => ServiceAreaPricing::normalizeIncludes($request->input('price_includes'), true),
            ]);
        }
    }
}
