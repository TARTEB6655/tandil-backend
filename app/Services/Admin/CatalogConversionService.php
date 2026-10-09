<?php

namespace App\Services\Admin;

use App\Http\Controllers\Shop\CartController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Convert admin catalog rows between Category (product catalog) and Service (service catalog).
 * Rows are archived (linked + hidden from lists), never hard-deleted.
 * Round-trip: converting back reactivates the paired archived row when possible.
 */
final class CatalogConversionService
{
    public static function convertCategoryToService(Category $category): Service
    {
        if ($category->converted_to_service_id !== null) {
            throw new \InvalidArgumentException('This category was already converted to a service.');
        }

        $pairedService = Service::query()
            ->where('converted_to_category_id', $category->id)
            ->first();

        if ($pairedService !== null) {
            return self::reactivateServiceFromCategory($category, $pairedService);
        }

        return DB::transaction(function () use ($category) {
            $slug = self::uniqueServiceSlug((string) $category->slug);

            $service = Service::create([
                'vendor_id' => $category->vendor_id,
                'name' => $category->name,
                'slug' => $slug,
                'description' => $category->description,
                'image' => self::copyPublicImage($category->image, 'services'),
                'icon' => $category->icon,
                'is_active' => (bool) ($category->is_active ?? true),
                'contractor_signup_enabled' => (bool) ($category->contractor_signup_enabled ?? true),
                'category_id' => $category->parent_id ? (int) $category->parent_id : null,
                'sort_order' => (int) ($category->sort_order ?? 0),
                'pricing_type' => 'fixed',
                'price' => null,
                'price_includes' => null,
            ]);

            self::migrateCategoryProductsToService($category, $service);

            Service::query()
                ->where('category_id', $category->id)
                ->where('id', '!=', $service->id)
                ->update(['category_id' => null]);

            $category->forceFill([
                'is_active' => false,
                'converted_to_service_id' => $service->id,
            ])->saveQuietly();

            return $service->fresh(['category']);
        });
    }

    public static function convertServiceToCategory(Service $service): Category
    {
        if ($service->converted_to_category_id !== null) {
            throw new \InvalidArgumentException('This service was already converted to a category.');
        }

        $pairedCategory = Category::query()
            ->where('converted_to_service_id', $service->id)
            ->first();

        if ($pairedCategory !== null) {
            return self::reactivateCategoryFromService($service, $pairedCategory);
        }

        return DB::transaction(function () use ($service) {
            $slug = self::uniqueCategorySlug((string) $service->slug);
            $defaultShipping = CartController::getEffectiveShippingAmount();
            $defaultTax = CartController::getEffectiveTaxPercent();

            $category = Category::create([
                'vendor_id' => $service->vendor_id,
                'parent_id' => $service->category_id ? (int) $service->category_id : null,
                'name' => $service->name,
                'slug' => $slug,
                'description' => $service->description,
                'image' => self::copyPublicImage($service->image, 'categories'),
                'icon' => $service->icon,
                'is_active' => (bool) ($service->is_active ?? true),
                'contractor_signup_enabled' => (bool) ($service->contractor_signup_enabled ?? true),
                'sort_order' => (int) ($service->sort_order ?? 0),
                'shipping_cost' => round(max(0, $defaultShipping), 2),
                'tax_percentage' => round(max(0, min(100, $defaultTax)), 2),
            ]);

            self::migrateServiceProductsToCategory($service, $category);

            $service->forceFill([
                'is_active' => false,
                'converted_to_category_id' => $category->id,
            ])->saveQuietly();

            return $category->fresh();
        });
    }

    private static function reactivateCategoryFromService(Service $service, Category $category): Category
    {
        return DB::transaction(function () use ($service, $category) {
            self::migrateServiceProductsToCategory($service, $category);

            $category->forceFill([
                'name' => $service->name,
                'description' => $service->description,
                'icon' => $service->icon ?? $category->icon,
                'sort_order' => (int) ($service->sort_order ?? $category->sort_order),
                'is_active' => true,
                'converted_to_service_id' => null,
            ])->saveQuietly();

            $service->forceFill([
                'is_active' => false,
                'converted_to_category_id' => $category->id,
            ])->saveQuietly();

            return $category->fresh();
        });
    }

    private static function reactivateServiceFromCategory(Category $category, Service $service): Service
    {
        return DB::transaction(function () use ($category, $service) {
            self::migrateCategoryProductsToService($category, $service);

            $service->forceFill([
                'name' => $category->name,
                'description' => $category->description,
                'icon' => $category->icon ?? $service->icon,
                'sort_order' => (int) ($category->sort_order ?? $service->sort_order),
                'is_active' => true,
                'converted_to_category_id' => null,
            ])->saveQuietly();

            Service::query()
                ->where('category_id', $category->id)
                ->where('id', '!=', $service->id)
                ->update(['category_id' => null]);

            $category->forceFill([
                'is_active' => false,
                'converted_to_service_id' => $service->id,
            ])->saveQuietly();

            return $service->fresh(['category']);
        });
    }

    private static function migrateCategoryProductsToService(Category $category, Service $service): void
    {
        Product::query()
            ->where('category_id', $category->id)
            ->orderBy('id')
            ->each(function (Product $product) use ($service): void {
                $product->forceFill([
                    'type' => 'service',
                    'requires_shipping' => false,
                ])->saveQuietly();
                $product->services()->syncWithoutDetaching([(int) $service->id]);
            });
    }

    private static function migrateServiceProductsToCategory(Service $service, Category $category): void
    {
        $productIds = $service->products()->pluck('products.id')->map(fn ($id) => (int) $id)->all();

        if ($productIds !== []) {
            Product::query()
                ->whereIn('id', $productIds)
                ->update([
                    'type' => 'product',
                    'category_id' => $category->id,
                    'requires_shipping' => true,
                ]);
            $service->products()->detach();
        }
    }

    private static function uniqueServiceSlug(string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'service';
        $slug = Str::slug($slug);
        if ($slug === '') {
            $slug = 'service';
        }
        $original = $slug;
        $counter = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private static function uniqueCategorySlug(string $baseSlug): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'category';
        $slug = Str::slug($slug);
        if ($slug === '') {
            $slug = 'category';
        }
        $original = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private static function copyPublicImage(?string $path, string $folder): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (! \Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $folder.'/'.Str::uuid()->toString();
        if ($extension !== '') {
            $target .= '.'.strtolower($extension);
        }

        \Illuminate\Support\Facades\Storage::disk('public')->copy($path, $target);

        return $target;
    }
}
