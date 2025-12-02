<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',    // optional: quick reference
        'status',  // active / inactive
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // HR employee record
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    // Client → Subscriptions
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'client_id');
    }

    // Technician → Visits assigned
    public function visits()
    {
        return $this->hasMany(Visit::class, 'technician_id');
    }

    // Shopping cart
    public function cart()
    {
        return $this->hasMany(Cart::class);
    }

    // Orders
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SUPERVISOR RELATIONS
    |--------------------------------------------------------------------------
    */

    // Areas supervised by this user
    public function supervisedAreas()
    {
        return $this->belongsToMany(Area::class, 'area_supervisor', 'user_id', 'area_id');
    }

    /*
    |--------------------------------------------------------------------------
    | ROLE HELPERS
    |--------------------------------------------------------------------------
    */

    public function isTechnician()    { return $this->hasRole('technician'); }
    public function isSupervisor()     { return $this->hasRole('supervisor'); }
    public function isAreaManager()    { return $this->hasRole('area_manager'); }
    public function isAdmin()          { return $this->hasRole('admin'); }
    public function isClient()         { return $this->hasRole('client'); }
}
