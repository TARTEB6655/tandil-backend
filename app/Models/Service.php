<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'is_active',
        'category_id',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['image_url', 'coming_soon'];

    public function getComingSoonAttribute(): bool
    {
        return isset($this->attributes['is_active']) && ! (bool) $this->attributes['is_active'];
    }

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

    public function scopeForVendorCatalog($query, ?int $vendorId)
    {
        return $query->where(function ($q) use ($vendorId) {
            $q->whereNull('vendor_id');
            if ($vendorId !== null) {
                $q->orWhere('vendor_id', $vendorId);
            }
        });
    }

    public function vendorAccount()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Products linked to this service (many-to-many).
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_service');
    }
}
