<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractorCity extends Model
{
    protected $fillable = [
        'emirate_id',
        'name',
        'name_ar',
        'is_active',
        'contractor_signup_enabled',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'contractor_signup_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function emirate(): BelongsTo
    {
        return $this->belongsTo(Emirate::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'emirate_id' => $this->emirate_id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
