<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'amount',
        'reason',
        'status',
        'credited_at',
        'expires_at',
        'forfeited_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credited_at' => 'datetime',
        'expires_at' => 'datetime',
        'forfeited_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
