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
        if (! $this->image_path) {
            return null;
        }
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        $normalized = ltrim(str_replace('\\', '/', $this->image_path), '/');
        if (str_starts_with($normalized, 'media/')) {
            return asset($normalized);
        }

        // Option images are uploaded via store('product-options', 'public'),
        // so the canonical URL should come from the public disk.
        if (Storage::disk('public')->exists($normalized)) {
            return asset(ltrim(Storage::disk('public')->url($normalized), '/'));
        }

        // Backward compatibility for older deployments that expose files via /media.
        return asset('media/' . $normalized);
    }
}
