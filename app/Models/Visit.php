<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visit extends Model
{
    use HasFactory;
    protected $fillable = [
        'subscription_id','technician_id','scheduled_date','status'
    ];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function technician()
    {
        return $this->belongsTo(Employee::class, 'technician_id');
    }

    public function photos()
    {
        return $this->hasMany(VisitPhoto::class);
    }

    public function report()
    {
        return $this->hasOne(Report::class);
    }
}
