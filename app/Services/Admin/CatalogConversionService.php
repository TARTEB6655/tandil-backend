<?php

namespace App\Services\Admin;

use App\Http\Controllers\Shop\CartController;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Convert admin catalog rows between Category (product catalog) and Service (service catalog).
 */
final class CatalogConversionService
{
    public static function convertCategoryToService(Category $category): Service
    {
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

            Service::query()
                ->where('category_id', $category->id)
                ->where('id', '!=', $service->id)
                ->update(['category_id' => null]);

            $category->forceFill(['is_active' => false])->saveQuietly();

            return $service->fresh(['category']);
        });
    }

    public static function convertServiceToCategory(Service $service): Category
    {
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

            if ($service->image && Storage::disk('public')->exists($service->image)) {
                Storage::disk('public')->delete($service->image);
            }
            $service->delete();

            return $category->fresh();
        });
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
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $folder.'/'.Str::uuid()->toString();
        if ($extension !== '') {
            $target .= '.'.strtolower($extension);
        }

        Storage::disk('public')->copy($path, $target);

        return $target;
    }
}
