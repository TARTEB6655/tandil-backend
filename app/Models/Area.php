<?php

namespace App\Models;

use App\Support\VisitAreaResolver;
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
        'location',
        'country',
        'is_active',
        'priority',
        'latitude',
        'longitude',
        'service_radius_km',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'service_radius_km' => 'float',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => VisitAreaResolver::clearOperationalAreasCache());
        static::deleted(fn () => VisitAreaResolver::clearOperationalAreasCache());
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Supervisors assigned to this area (pivot area_supervisor; role enforced in controller)
    public function supervisors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'area_supervisor',
            'area_id',
            'user_id'
        );
    }

    // Technicians assigned to this area (pivot area_technician; role enforced in controller)
    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'area_technician',
            'area_id',
            'user_id'
        );
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
