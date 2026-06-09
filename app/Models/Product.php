<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'vendor_id', 'name', 'vendor', 'type', 'product_type', 'sku', 'barcode', 'description',
        'price', 'compare_at_price', 'cost_per_item', 'stock', 'status', 'is_featured', 'sort_order',
        'track_quantity', 'allow_backorder', 'weight', 'weight_unit', 'tags',
        'meta_title', 'meta_description', 'handle', 'requires_shipping', 'taxable', 'image',
        'estimated_arrival', 'job_duration',
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
        'compare_at_price' => 'float',
        'cost_per_item' => 'float',
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
        // Fallback to first available image relation when no primary is marked
        if ($this->relationLoaded('firstImage')) {
            $first = $this->getRelation('firstImage');
            if ($first && $first->image_path) {
                return $this->buildImageUrl($first->image_path);
            }
        }
        // Fallback to image field
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
