<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'product_id',
        'rating',
        'comment',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Service (order-level) reviews have no product_id.
     */
    public function scopeService($query)
    {
        return $query->whereNull('product_id');
    }

    /**
     * Per-product reviews.
     */
    public function scopeForProducts($query)
    {
        return $query->whereNotNull('product_id');
    }
}
