<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $appends = ['image_url'];

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
     * Otherwise builds a full URL using asset() so when domain or ASSET_URL
     * changes in the future, all API responses stay correct (APP_URL / ASSET_URL).
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
        $imagePath = ltrim(str_replace('\\', '/', $imagePath), '/');
        // Direct serve URL: /media/ serves files (no redirect); app can also build {{origin}}/media/{{path}} from relative image_path
        return asset('media/' . $imagePath);
    }

    /**
     * Ensure URL uses clean /media/ path (no "storage" in URL; professional public path).
     */
    public static function normalizeStorageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (str_contains($url, '/storage/')) {
            return str_replace('/storage/', '/media/', $url);
        }
        if (str_contains($url, '/app-storage/')) {
            return str_replace('/app-storage/', '/media/', $url);
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
