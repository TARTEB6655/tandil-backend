<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','total_amount','payment_status','payment_reference','paid_at','order_status'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
