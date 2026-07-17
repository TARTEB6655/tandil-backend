<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_NOT_ENOUGH_POINTS = 'not_enough_points';

    protected $fillable = [
        'title',
        'description',
        'points_required',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'points_required' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
