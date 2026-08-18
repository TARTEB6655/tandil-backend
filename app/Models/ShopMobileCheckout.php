<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopMobileCheckout extends Model
{
    protected $fillable = [
        'user_id',
        'coupon_id',
        'coupon_code',
        'coupon_merchandise_discount',
        'coupon_shipping_discount',
        'fingerprint',
        'checkout_ref',
        'stripe_payment_intent_id',
        'stripe_account_fingerprint',
        'source',
        'currency',
        'amount_minor',
        'lines_json',
        'shipping_json',
        'subtotal_amount',
        'tax_amount',
        'tax_percent',
        'shipping_amount',
        'total_amount',
        'wallet_amount_applied',
        'special_instructions',
        'booking_date',
        'booking_slot',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'lines_json' => 'array',
            'shipping_json' => 'array',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'wallet_amount_applied' => 'decimal:2',
            'coupon_merchandise_discount' => 'decimal:2',
            'coupon_shipping_discount' => 'decimal:2',
            'booking_date' => 'date:Y-m-d',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
