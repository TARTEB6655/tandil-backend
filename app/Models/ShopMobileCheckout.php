<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopMobileCheckout extends Model
{
    protected $fillable = [
        'user_id',
        'fingerprint',
        'checkout_ref',
        'stripe_payment_intent_id',
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
        'special_instructions',
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
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
