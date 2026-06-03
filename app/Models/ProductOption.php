<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_option_group_id',
        'label',
        'subtitle',
        'price_modifier',
        'image_path',
        'sort_order',
    ];

    protected $casts = [
        'price_modifier' => 'float',
        'sort_order'     => 'integer',
    ];

    protected $appends = ['image_url'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductOptionGroup::class, 'product_option_group_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return self::buildFullUrl($this->image_path);
    }

    /**
     * Full absolute URL for option images (same host logic as ProductImage::buildFullUrl).
     */
    public static function buildFullUrl(?string $imagePath): ?string
    {
        if (! $imagePath || ! is_string($imagePath)) {
            return null;
        }

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        $path = ltrim(str_replace('\\', '/', $imagePath), '/');

        if (str_starts_with($path, 'media/')) {
            $path = substr($path, strlen('media/'));
        }

        if (str_contains($path, 'product-options/')) {
            $path = substr($path, strpos($path, 'product-options/'));
        } elseif (! str_starts_with($path, 'product-options/')) {
            $path = 'product-options/' . $path;
        }

        $mediaPath = 'media/' . $path;

        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/' . $mediaPath;
        }

        if (Storage::disk('public')->exists($path)) {
            return asset(ltrim(Storage::disk('public')->url($path), '/'));
        }

        return asset($mediaPath);
    }

    /**
     * Standard API shape for product detail / list responses.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id'             => $this->id,
            'temp_key'       => 'opt_'.$this->id,
            'label'          => $this->label,
            'subtitle'       => $this->subtitle,
            'price_modifier' => (float) $this->price_modifier,
            'image_path'     => $this->image_path,
            'image_url'      => self::buildFullUrl($this->image_path),
            'sort_order'     => (int) $this->sort_order,
        ];
    }
}
