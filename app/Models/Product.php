<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'vendor', 'type', 'sku', 'barcode', 'description',
        'price', 'compare_at_price', 'cost_per_item', 'stock', 'status',
        'track_quantity', 'allow_backorder', 'weight', 'weight_unit', 'tags',
        'meta_title', 'meta_description', 'handle', 'requires_shipping', 'taxable', 'image',
    ];

    protected $casts = [
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

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
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
        // Fallback to image field
        if ($this->image) {
            return $this->buildImageUrl($this->image, 'products/');
        }
        return null;
    }

    /**
     * Build full image URL from path.
     */
    private function buildImageUrl(string $path, string $prefix = ''): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if ($prefix && strpos($path, $prefix) !== 0) {
            $path = $prefix . $path;
        }
        return asset('media/' . ltrim(str_replace('\\', '/', $path), '/'));
    }

    /**
     * Helper for views - same as image_url accessor.
     */
    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }
}
