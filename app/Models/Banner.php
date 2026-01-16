<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title',
        'image',
        'link',
        'action_type',
        'action_value',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the image URL
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            // Check if image is a full URL (compatible with PHP 7.x)
            if (filter_var($this->image, FILTER_VALIDATE_URL) || substr($this->image, 0, 4) === 'http') {
                return $this->image;
            }
            return asset('storage/' . $this->image);
        }
        return null;
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
}
