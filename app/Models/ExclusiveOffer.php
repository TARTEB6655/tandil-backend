<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExclusiveOffer extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'discount_type',
        'discount_value',
        'applies_to',
        'start_date',
        'end_date',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the image URL (uses /media/ path like Banner/Category for display).
     */
    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image) || ! is_string($this->image)) {
            return null;
        }
        if (filter_var($this->image, FILTER_VALIDATE_URL) || str_starts_with($this->image, 'http')) {
            return $this->image;
        }
        $path = ltrim(str_replace('\\', '/', $this->image), '/');
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/storage/' . $path;
        }
        return asset('storage/' . $path);
    }

    /**
     * Scope: active offers only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: order by sort_order then created_at.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');
    }

    /**
     * Scope: currently valid by date (start_date <= today <= end_date, or no dates set).
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->where(function ($q2) use ($today) {
                $q2->whereNull('start_date')->orWhere('start_date', '<=', $today);
            })->where(function ($q2) use ($today) {
                $q2->whereNull('end_date')->orWhere('end_date', '>=', $today);
            });
        });
    }

    /**
     * Products linked to this exclusive offer (many-to-many).
     */
    public function products()
    {
        return $this->belongsToMany(\App\Models\Product::class, 'exclusive_offer_product');
    }
}
