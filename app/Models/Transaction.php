<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'transactionable_type',
        'transactionable_id',
        'type',
        'gateway',
        'payment_method',
        'amount',
        'currency',
        'status',
        'gateway_transaction_id',
        'gateway_response',
        'notes',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the parent transactionable model (order or subscription).
     */
    public function transactionable()
    {
        return $this->morphTo();
    }
}
