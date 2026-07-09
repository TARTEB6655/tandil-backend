<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPartnershipTier extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'badge_color',
        'price',
        'currency',
        'duration_months',
        'required_products_min',
        'required_products_max',
        'max_product_listings',
        'max_partner_product_images',
        'marketing_exposure',
        'social_media_posts_per_month',
        'app_banners',
        'home_banner_size',
        'benefits',
        'features',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'benefits' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function partnerships(): HasMany
    {
        return $this->hasMany(VendorPartnership::class, 'tier_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(VendorPartnershipApplication::class, 'tier_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasUnlimitedProducts(): bool
    {
        return $this->max_product_listings === null;
    }

    public function featureEnabled(string $key): bool
    {
        return (bool) data_get($this->features, $key, false);
    }
}
