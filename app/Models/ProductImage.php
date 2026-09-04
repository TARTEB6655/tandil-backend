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
     * Build a full absolute image URL from path (uses request host when available so URLs work behind proxy).
     */
    public static function buildFullUrl(?string $imagePath): ?string
    {
        if (! $imagePath || ! is_string($imagePath)) {
            return null;
        }
        $path = $imagePath;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (strpos($path, 'products/') !== 0) {
            $path = 'products/' . $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = 'media/' . $path;
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/' . $path;
        }
        return asset($path);
    }

    /**
     * Small cart/list thumbnail URL (served via /media/{path}?w=… with on-disk cache).
     */
    public static function buildThumbUrl(?string $imagePath, int $width = 192): ?string
    {
        $full = self::buildFullUrl($imagePath);
        if ($full === null) {
            return null;
        }
        $width = max(48, min(640, $width));
        $sep = str_contains($full, '?') ? '&' : '?';

        return $full.$sep.'w='.$width;
    }

    /**
     * Get the full URL for the image.
     * Uses request host when available so API image URLs work behind proxy / correct domain.
     */
    public function getImageUrl()
    {
        return self::buildFullUrl($this->image_path);
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

    /**
     * Return unique images by image_path (first occurrence wins, order preserved).
     * Use in API responses so duplicate DB rows (e.g. from old bug) don't return duplicate images.
     */
    public static function uniqueByPath($images): array
    {
        if ($images === null) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach ($images as $img) {
            $path = $img->image_path ?? '';
            if ($path !== '' && ! isset($seen[$path])) {
                $seen[$path] = true;
                $out[] = $img;
            }
        }
        return $out;
    }

    /**
     * Return deduplicated images as API-ready array (no duplication, full image_url for each).
     */
    public static function toApiImagesArray($images): array
    {
        $unique = self::uniqueByPath($images);
        $out = [];
        foreach ($unique as $img) {
            $out[] = [
                'id' => $img->id,
                'image_path' => $img->image_path,
                'image_url' => self::buildFullUrl($img->image_path),
                'sort_order' => (int) $img->sort_order,
                'is_primary' => (bool) $img->is_primary,
            ];
        }
        return $out;
    }
}
