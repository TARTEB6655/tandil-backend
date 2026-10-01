<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTopUp extends Model
{
    protected $table = 'wallet_topups';

    protected $fillable = [
        'user_id',
        'amount',
        'amount_minor',
        'currency',
        'stripe_payment_intent_id',
        'status',
        'payment_method',
        'consumed_at',
        'wallet_credit_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_minor' => 'integer',
        'consumed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function walletCredit(): BelongsTo
    {
        return $this->belongsTo(WalletCredit::class, 'wallet_credit_id');
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null || $this->status === 'succeeded';
    }
}
