<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopBuyNowContext extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'user_id';

    protected $table = 'shop_buy_now_contexts';

    protected $fillable = [
        'user_id',
        'payload',
        'updated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'updated_at' => 'datetime',
    ];
}
