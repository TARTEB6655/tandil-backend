<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'gateway',
        'provider_method_id',
        'provider_customer_id',
        'label',
        'brand',
        'last4',
        'expiry_month',
        'expiry_year',
        'email',
        'is_default',
        'meta',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

