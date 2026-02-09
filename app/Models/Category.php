<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];


    protected $appends = ['image_url', 'coming_soon'];

    public function getComingSoonAttribute(): bool
    {
        return isset($this->attributes['is_active']) && ! (bool) $this->attributes['is_active'];
    }

    /**
     * Get the full URL for the category image.
     * Uses request host when in HTTP context (so live/proxy URLs are correct), else asset().
     */
    public function getImageUrlAttribute(): ?string
    {
        $image = $this->attributes['image'] ?? null;
        if (empty($image) || ! is_string($image)) {
            return null;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        $path = ltrim(str_replace('\\', '/', $image), '/');
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/media/' . $path;
        }
        return asset('media/' . $path);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
