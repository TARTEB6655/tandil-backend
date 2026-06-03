<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOptionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'subtitle',
        'input_type',  // single | multi
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_option_group_id')
                    ->orderBy('sort_order');
    }

    /**
     * Standard API shape including all options (labels, images, prices).
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $options = [];
        if ($this->relationLoaded('options')) {
            $options = $this->options->map(fn (ProductOption $opt) => $opt->toApiArray())->values()->all();
        }

        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'subtitle'    => $this->subtitle,
            'input_type'  => $this->input_type,
            'is_required' => (bool) $this->is_required,
            'sort_order'  => (int) $this->sort_order,
            'options'     => $options,
        ];
    }
}
