<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    // Specify the fields that can be mass-assigned
    protected $fillable = [
        'category_id',
        'name',
        'vendor',
        'type',
        'sku',
        'barcode',
        'description',
        'price',
        'compare_at_price',
        'cost_per_item',
        'stock',
        'status',
        'track_quantity',
        'allow_backorder',
        'weight',
        'weight_unit',
        'tags',
        'meta_title',
        'meta_description',
        'handle',
        'requires_shipping',
        'taxable',
        'image',
    ];

    /**
     * Append image_url to array/JSON representation.
     */
    protected $appends = ['image_url'];

    /**
     * Define relationship with Category model
     * Each product belongs to one category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get all images for this product.
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the primary image.
     */
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    /**
     * Get the full URL for the product image.
     * Works both locally and in production.
     * This is used by the $appends array for API responses.
     */
    public function getImageUrlAttribute()
    {
        // Try primary image first (need to load relationship if not already loaded)
        if (!$this->relationLoaded('primaryImage')) {
            $this->load('primaryImage');
        }
        
        if ($this->primaryImage && $this->primaryImage->image_path) {
            return $this->primaryImage->getImageUrl();
        }
        
        // Fall back to main image field
        if ($this->image) {
            $imagePath = $this->image;
            if (strpos($imagePath, 'products/') !== 0) {
                $imagePath = 'products/' . $imagePath;
            }
            
            $url = asset('storage/' . $imagePath);
            
            // For local development, ensure port 8000 is included if not already
            if (app()->environment('local') && strpos($url, ':8000') === false && strpos($url, 'localhost') !== false) {
                $url = str_replace('http://localhost', 'http://localhost:8000', $url);
            }
            
            return $url;
        }
        
        return null;
    }

    /**
     * Get the image URL (helper method for views).
     * Returns the primary image URL or the main image URL.
     */
    public function getImageUrl()
    {
        // Try primary image first (need to load relationship if not already loaded)
        if (!$this->relationLoaded('primaryImage')) {
            $this->load('primaryImage');
        }
        
        if ($this->primaryImage && $this->primaryImage->image_path) {
            return $this->primaryImage->getImageUrl();
        }
        
        // Fall back to main image field
        if ($this->image) {
            $imagePath = $this->image;
            if (strpos($imagePath, 'products/') !== 0) {
                $imagePath = 'products/' . $imagePath;
            }
            
            $url = asset('storage/' . $imagePath);
            
            // For local development, ensure port 8000 is included if not already
            if (app()->environment('local') && strpos($url, ':8000') === false && strpos($url, 'localhost') !== false) {
                $url = str_replace('http://localhost', 'http://localhost:8000', $url);
            }
            
            return $url;
        }
        
        return null;
    }

    /**
     * Get all image URLs for API responses.
     */
    public function getImageUrlsAttribute()
    {
        $urls = [];
        
        // Load relationships if not already loaded
        if (!$this->relationLoaded('primaryImage')) {
            $this->load('primaryImage');
        }
        if (!$this->relationLoaded('images')) {
            $this->load('images');
        }
        
        // Add primary image
        if ($this->primaryImage && $this->primaryImage->image_path) {
            $urls[] = [
                'url' => $this->primaryImage->getImageUrl(),
                'is_primary' => true,
                'path' => $this->primaryImage->image_path,
            ];
        }
        
        // Add other images
        foreach ($this->images as $image) {
            if (!$image->is_primary) {
                $urls[] = [
                    'url' => $image->getImageUrl(),
                    'is_primary' => false,
                    'path' => $image->image_path,
                ];
            }
        }
        
        return $urls;
    }
}
