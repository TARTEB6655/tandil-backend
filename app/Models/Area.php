<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Supervisors assigned to this area
    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'area_supervisor',
            'area_id',
            'user_id'
        )->where('role', 'supervisor');
    }

    // Technicians assigned to this area
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'area_technician',
            'area_id',
            'user_id'
        )->where('role', 'technician');
    }

    // Visits happening in this area
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class, 'area_id');
    }

    // Complaints related to visits in this area
    public function complaints()
    {
        return $this->hasManyThrough(
            Complaint::class,
            Visit::class,
            'area_id',      // Visit.area_id
            'visit_id',     // Complaint.visit_id
            'id',           // Area.id
            'id'            // Visit.id
        );
    }
}
