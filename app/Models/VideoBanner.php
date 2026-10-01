<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VideoBanner extends Model
{
    protected $fillable = [
        'title',
        'video_path',
        'poster_path',
        'badge_text',
        'button_text',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Full URL for the video (uses /media/ like Banner image_url).
     */
    public function getVideoUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->video_path);
    }

    /**
     * Full URL for the poster image shown while the video buffers.
     */
    public function getPosterUrlAttribute(): ?string
    {
        return $this->resolveMediaUrl($this->poster_path);
    }

    /**
     * Stored video size in bytes (helps the app show progress / decide preload strategy).
     */
    public function getVideoSizeBytesAttribute(): ?int
    {
        $path = $this->video_path;
        if (! $path || filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'http')) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $path), '/');
        if (! Storage::disk('public')->exists($relative)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($relative);
        clearstatcache(true, $fullPath);
        $size = is_file($fullPath) ? filesize($fullPath) : false;

        return $size !== false ? (int) $size : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicApiArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'video_url' => $this->video_url,
            'poster_url' => $this->poster_url,
            'video_size_bytes' => $this->video_size_bytes,
            'badge_text' => $this->badge_text,
            'button_text' => $this->button_text,
            'is_active' => (bool) $this->is_active,
        ];
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
