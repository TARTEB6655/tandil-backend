<?php

namespace App\Models;

use App\Support\BannerLinkResolver;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'description',
        'image',
        'link',
        'action_type',
        'action_value',
        'button_text',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Home/list banner URL (cached thumb via /media/…?w=640).
     */
    public function getImageUrlAttribute()
    {
        return \App\Support\MediaUrl::thumb($this->image, 640)
            ?? \App\Support\MediaUrl::full($this->image);
    }

    /**
     * Scope to get active banners
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'asc')->orderBy('created_at', 'desc');
    }

    /**
     * Safe click URL for web dashboard / app (null = not clickable).
     */
    public function getResolvedHrefAttribute(): ?string
    {
        return BannerLinkResolver::resolve($this);
    }

    public function getResolvedHrefIsExternalAttribute(): bool
    {
        return BannerLinkResolver::isExternalUrl($this->resolved_href);
    }
}
