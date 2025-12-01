<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id','plan','start_date','end_date','amount',
        'payment_status','payment_reference','paid_at','total_visits','completed_visits'
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class);
    }
}
