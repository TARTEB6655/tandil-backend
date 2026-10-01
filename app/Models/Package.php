<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'type',
        'price',
        'image',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public const TYPE_COMBINED = 'combined';
    public const TYPE_FRUIT = 'fruit';
    public const TYPE_VEGETABLE = 'vegetable';

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->attributes['image'] ?? null;

        return \App\Support\MediaUrl::thumb(is_string($image) ? $image : null, 384)
            ?? \App\Support\MediaUrl::full(is_string($image) ? $image : null);
    }

    protected $appends = ['image_url'];
}
