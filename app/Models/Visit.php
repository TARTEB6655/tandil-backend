<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'technician_id',
        'supervisor_id',
        'area_id',
        'scheduled_date',
        'completed_date',
        'status',
        'approved_by',
        'approved_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'approved_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function photos()
    {
        return $this->hasMany(VisitPhoto::class);
    }

    public function report()
    {
        return $this->hasOne(Report::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
