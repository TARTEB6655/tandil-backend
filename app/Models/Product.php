<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'vendor', 'type', 'product_type', 'sku', 'barcode', 'description',
        'price', 'compare_at_price', 'cost_per_item', 'stock', 'status', 'is_featured',
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
        'price' => 'float',
        'compare_at_price' => 'float',
        'cost_per_item' => 'float',
    ];

    // Optimized: Only append image_url (primary_image loaded via relation)
    protected $appends = ['image_url'];

    public function category()
    {
        return $this->belongsTo(Category::class);
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
