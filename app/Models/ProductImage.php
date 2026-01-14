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
     * Works both locally and in production.
     */
    public function getImageUrl()
    {
        if ($this->image_path) {
            // Ensure the path is correct
            $imagePath = $this->image_path;
            if (strpos($imagePath, 'products/') !== 0) {
                $imagePath = 'products/' . $imagePath;
            }
            
            // Use asset() which automatically handles the base URL
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
     * Accessor for image URL.
     */
    public function getImageUrlAttribute()
    {
        return $this->getImageUrl();
    }
}
