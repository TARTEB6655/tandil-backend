<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'is_active',
        'usage_limit',
        'usage_limit_per_user',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_active' => 'boolean',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function paidOrdersCount(): int
    {
        return $this->orders()->where('payment_status', 'paid')->count();
    }

    public function paidOrdersCountForUser(int $userId): int
    {
        return $this->orders()
            ->where('user_id', $userId)
            ->where('payment_status', 'paid')
            ->count();
    }
}
