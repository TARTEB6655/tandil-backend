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
        'extra_emails',
        'phone',
        'extra_phones',
        'profile_picture',
        'password',
        'role',    // optional: quick reference
        'status',  // active / inactive
        'wallet_balance',
        'wallet_forfeited_total',
    ];

    protected $appends = [
        'profile_picture_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'extra_emails' => 'array',
        'extra_phones' => 'array',
        'wallet_balance' => 'decimal:2',
        'wallet_forfeited_total' => 'decimal:2',
    ];

    /**
     * Full URL for profile picture (stored path under storage/app/public, served via /media/).
     * Used for all roles (technician, client, admin, etc.).
     */
    public function getProfilePictureUrlAttribute(): ?string
    {
        $path = $this->attributes['profile_picture'] ?? null;
        if (empty($path) || ! is_string($path)) {
            return null;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return request()->getSchemeAndHttpHost() ? (rtrim(request()->getSchemeAndHttpHost(), '/') . '/media/' . $path) : asset('media/' . $path);
    }

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

    // Technician → Availability, breaks, vacations
    public function technicianAvailability()
    {
        return $this->hasOne(TechnicianAvailability::class);
    }

    public function technicianBreaks()
    {
        return $this->hasMany(TechnicianBreak::class);
    }

    public function technicianVacations()
    {
        return $this->hasMany(TechnicianVacation::class);
    }

    public function technicianBankAccounts()
    {
        return $this->hasMany(TechnicianBankAccount::class);
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

    public function walletCredits()
    {
        return $this->hasMany(WalletCredit::class);
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

    /** Get IDs of areas supervised by this user (avoids ambiguous column in join). */
    public function supervisedAreaIds(): array
    {
        return $this->supervisedAreas()->selectRaw('areas.id as id')->pluck('id')->toArray();
    }

    /** Technician: zones (areas) this user is assigned to (area_technician). */
    public function assignedAreas()
    {
        return $this->belongsToMany(Area::class, 'area_technician', 'user_id', 'area_id');
    }

    /** Saved reusable payment methods (PayPal vault token / card token, etc). */
    public function paymentMethods()
    {
        return $this->hasMany(UserPaymentMethod::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /** Scope: only users with status = active (for listings where inactive should be hidden). */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'active')
                ->orWhereNull('status');
        });
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
