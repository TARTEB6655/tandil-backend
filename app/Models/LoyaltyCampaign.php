<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyCampaign extends Model
{
    protected $fillable = [
        'title',
        'multiplier',
        'start_date',
        'end_date',
        'cities',
        'customer_targeting',
        'specific_customer_ids',
        'eligible_activities',
        'notes',
        'is_enabled',
    ];

    protected $casts = [
        'multiplier' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
        'specific_customer_ids' => 'array',
        'eligible_activities' => 'array',
        'is_enabled' => 'boolean',
    ];

    public function isLive(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $today = now()->toDateString();

        return $this->start_date?->format('Y-m-d') <= $today
            && $this->end_date?->format('Y-m-d') >= $today;
    }
}
