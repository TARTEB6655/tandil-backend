<?php

namespace App\Models;

use App\Enums\VendorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'vendor_id', 'name', 'vendor', 'type', 'product_type', 'sku', 'barcode', 'description',
        'price', 'pricing_type', 'price_includes', 'compare_at_price', 'cost_per_item', 'stock', 'status', 'is_featured', 'sort_order',
        'track_quantity', 'allow_backorder', 'weight', 'weight_unit', 'tags',
        'meta_title', 'meta_description', 'handle', 'requires_shipping', 'taxable', 'image',
        'estimated_arrival', 'job_duration', 'rating_average', 'rating_count',
    ];

    // Valid values for product_type column
    public const TYPE_SIMPLE   = 'simple';
    public const TYPE_VARIABLE = 'variable';

    protected $casts = [
        'product_type' => 'string',
        'is_featured' => 'boolean',
        'track_quantity' => 'boolean',
        'allow_backorder' => 'boolean',
        'requires_shipping' => 'boolean',
        'taxable' => 'boolean',
        'stock' => 'integer',
        'sort_order' => 'integer',
        'price' => 'float',
        'price_includes' => 'array',
        'compare_at_price' => 'float',
        'cost_per_item' => 'float',
        'rating_average' => 'float',
        'rating_count' => 'integer',
    ];

    /**
     * Order products for display by the admin-defined drag-and-drop position
     * (sort_order ASC), then newest first as a tie-breaker.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Max units a customer may buy/select for this product (stock ceiling).
     * No arbitrary cap (e.g. 10) — only available stock.
     */
    public function maxPurchaseQuantity(): int
    {
        return max(0, (int) ($this->stock ?? 0));
    }

    /**
     * Null when qty is allowed; otherwise an error message for API/UI.
     */
    public function quantityExceedsStockMessage(int $quantity): ?string
    {
        $max = $this->maxPurchaseQuantity();
        if ($quantity < 1) {
            return 'Quantity must be at least 1.';
        }
        if ($max <= 0) {
            return 'This product is out of stock.';
        }
        if ($quantity > $max) {
            return "Only {$max} unit(s) available in stock.";
        }

        return null;
    }

    /**
     * Products visible on the client shop / category screens.
     * Platform (admin) products: active only.
     * Vendor products: approved vendor + live marketplace listing only.
     */
    public function scopeVisibleInClientShop($query)
    {
        return $query
            ->where('products.status', 'active')
            ->where(function ($q) {
                $q->whereDoesntHave('vendorProduct')
                    ->orWhereHas('vendorProduct', function ($vendorProduct) {
                        $vendorProduct->marketplaceLive()
                            ->whereHas('vendor', fn ($vendor) => $vendor->where('status', VendorStatus::Approved->value));
                    });
            });
    }

    /**
     * Next available sort_order value. Scoped to a category when given so new
     * products are placed at the end of their category list.
     */
    public static function nextSortOrder(?int $categoryId = null): int
    {
        $query = static::query();
        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        return ((int) $query->max('sort_order')) + 1;
    }

    // Optimized: Only append image_url (primary_image loaded via relation)
    protected $appends = ['image_url'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function vendorAccount()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function vendorProduct()
    {
        return $this->hasOne(VendorProduct::class);
    }

    /**
     * Services this product is linked to (optional, many-to-many).
     */
    public function services()
    {
        return $this->belongsToMany(Service::class, 'product_service');
    }

    /**
     * Exclusive offers this product is linked to (many-to-many).
     */
    public function exclusiveOffers()
    {
        return $this->belongsToMany(\App\Models\ExclusiveOffer::class, 'exclusive_offer_product');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function timeSlots()
    {
        return $this->hasMany(ProductTimeSlot::class)->orderBy('sort_order')->orderBy('start_time');
    }

    public function blockedDates()
    {
        return $this->hasMany(ProductBlockedDate::class)->orderBy('date');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Recompute rating_average and rating_count from per-product reviews.
     */
    public function recalculateRating(): void
    {
        $stats = $this->reviews()
            ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg_rating')
            ->first();

        $count = (int) ($stats->cnt ?? 0);
        $average = $count > 0 ? round((float) $stats->avg_rating, 2) : 0.0;

        $this->forceFill([
            'rating_count' => $count,
            'rating_average' => $average,
        ])->saveQuietly();
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * First available image by sort order (fallback when no primary image is marked).
     */
    public function firstImage()
    {
        return $this->hasOne(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get primary_image from already-loaded relation (no extra query).
     */
    public function getPrimaryImageAttribute()
    {
        // Only return if relation already loaded - never trigger lazy load
        return $this->relationLoaded('primaryImage') ? $this->getRelation('primaryImage') : null;
    }

    /**
     * Get image URL without triggering lazy loads.
     */
    public function getImageUrlAttribute(): ?string
    {
        // Check already-loaded primaryImage relation first
        if ($this->relationLoaded('primaryImage')) {
            $primary = $this->getRelation('primaryImage');
            if ($primary && $primary->image_path) {
                return $this->buildImageUrl($primary->image_path);
            }
        }

        // Fallback to gallery images when primary is not marked
        if ($this->relationLoaded('images')) {
            $images = $this->getRelation('images');
            if ($images && $images->isNotEmpty()) {
                $primary = $images->firstWhere('is_primary', true);
                $candidate = $primary ?? $images->sortBy('sort_order')->first();
                if ($candidate && $candidate->image_path) {
                    return $this->buildImageUrl($candidate->image_path);
                }
            }
        }

        // Fallback to first available image relation when no primary is marked
        if ($this->relationLoaded('firstImage')) {
            $first = $this->getRelation('firstImage');
            if ($first && $first->image_path) {
                return $this->buildImageUrl($first->image_path);
            }
        }

        // Fallback to legacy image column
        if ($this->image) {
            return $this->buildImageUrl($this->image, 'products/');
        }

        return null;
    }

    /**
     * Build full image URL from path.
     */
    private function buildImageUrl(string $path, string $prefix = ''): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        // If DB already stores a public media path, keep it as-is.
        if (str_starts_with($normalized, 'media/')) {
            return asset($normalized);
        }
        // Support plain public images paths as well (e.g. images/logo.png).
        if (str_starts_with($normalized, 'images/')) {
            return asset($normalized);
        }

        if ($prefix && strpos($normalized, $prefix) !== 0) {
            $normalized = $prefix . $normalized;
        }

        // Enforce production media URL style requested by frontend/deployment.
        // Example: https://<host>/media/products/abc.jpg
        return asset('media/' . $normalized);
    }

    /**
     * Helper for views - same as image_url accessor.
     */
    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    /**
     * All product images for gallery UI (primary first, then sort_order). Deduped by path.
     *
     * @return array<int, array{id: int, url: string, is_primary: bool}>
     */
    public function getDisplayImageList(): array
    {
        $collection = $this->relationLoaded('images')
            ? $this->images
            : $this->images()->get();

        $unique = ProductImage::uniqueByPath($collection);
        usort($unique, function (ProductImage $a, ProductImage $b): int {
            if ($a->is_primary !== $b->is_primary) {
                return $b->is_primary <=> $a->is_primary;
            }

            return $a->sort_order <=> $b->sort_order;
        });

        $list = [];
        foreach ($unique as $img) {
            $url = $img->image_url;
            if ($url) {
                $list[] = [
                    'id' => (int) $img->id,
                    'url' => $url,
                    'is_primary' => (bool) $img->is_primary,
                ];
            }
        }

        if ($list === []) {
            $fallback = $this->getImageUrl();
            if ($fallback) {
                $list[] = ['id' => 0, 'url' => $fallback, 'is_primary' => true];
            }
        }

        return $list;
    }

    // ── Variable product relations ──────────────────────────────────────────

    public function optionGroups()
    {
        return $this->hasMany(ProductOptionGroup::class)->orderBy('sort_order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function isVariable(): bool
    {
        return $this->product_type === self::TYPE_VARIABLE;
    }
}
