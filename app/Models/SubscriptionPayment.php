<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    protected $fillable = [
        'subscription_id',
        'client_id',
        'action',
        'from_plan',
        'to_plan',
        'amount',
        'amount_minor',
        'currency',
        'stripe_payment_intent_id',
        'status',
        'payment_method',
        'consumed_at',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'consumed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null || $this->status === 'succeeded';
    }
}
