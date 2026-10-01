<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'stock',
        'is_default',
        'label',
    ];

    protected $casts = [
        'price'      => 'float',
        'stock'      => 'integer',
        'is_default' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOption::class,
            'product_variant_options',
            'product_variant_id',
            'product_option_id'
        );
    }

    /**
     * Resolved price: variant price takes priority, falls back to product base price.
     */
    public function resolvedPrice(): float
    {
        return $this->price ?? (float) ($this->product->price ?? 0);
    }
}
