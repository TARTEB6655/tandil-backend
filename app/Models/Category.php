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
    ];

    /**
     * Append image_url to array/JSON representation.
     */
    protected $appends = ['image_url'];

    /**
     * Get the full URL for the category image.
     * Returns relative /media/ path for local storage so images load on any host/port.
     */
    public function getImageUrlAttribute(): ?string
    {
        $image = $this->attributes['image'] ?? null;
        if (empty($image) || !is_string($image)) {
            return null;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        $path = ltrim(str_replace('\\', '/', $image), '/');
        return '/media/' . $path;
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
