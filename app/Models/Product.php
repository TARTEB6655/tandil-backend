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
     * Accessor for image URL
     * If image field stores filename, return full URL.
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return url('storage/products/' . $this->image);
        }
        return null;
    }
}
