<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoBanner extends Model
{
    protected $fillable = [
        'title',
        'video_path',
        'poster_path',
        'badge_text',
        'button_text',
        'button_link',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Full URL for the video (uses /media/ like Banner image_url). Returns the
     * value as-is when it is already an absolute URL (external video).
     */
    public function getVideoUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->video_path);
    }

    /**
     * Full URL for the poster image.
     */
    public function getPosterUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->poster_path);
    }

    private function resolveMediaUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL) || substr($path, 0, 4) === 'http') {
            return $path;
        }

        return asset('media/' . ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
