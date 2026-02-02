<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'sort_order',
        'is_primary',
    ];

    /**
     * Get the product that owns the image.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the full URL for the image.
     * Returns image_path as-is when it is already a full URL (http/https).
     * Otherwise builds storage URL for local paths.
     */
    public function getImageUrl()
    {
        if (! $this->image_path) {
            return null;
        }
        $imagePath = $this->image_path;
        // Already a full URL (e.g. from image_urls)
        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }
        if (strpos($imagePath, 'products/') !== 0) {
            $imagePath = 'products/' . $imagePath;
        }
        $url = asset('storage/' . $imagePath);
        if (app()->environment('local') && strpos($url, ':8000') === false && strpos($url, 'localhost') !== false) {
            $url = str_replace('http://localhost', 'http://localhost:8000', $url);
        }
        return $url;
    }

    /**
     * Accessor for image URL.
     */
    public function getImageUrlAttribute()
    {
        return $this->getImageUrl();
    }
}
