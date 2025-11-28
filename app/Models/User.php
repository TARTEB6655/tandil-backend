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

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',        // quick reference (optional)
        'status',      // active / inactive / suspended
    ];

    /**
     * Hidden fields
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Cast fields
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relationships
     */

    // HR employee record
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    // Client's subscriptions
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'client_id');
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
}
