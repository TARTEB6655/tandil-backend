<?php

namespace App\Models;

use App\Enums\VendorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'status',
        'approved_at',
        'rejected_at',
        'suspended_at',
        'rejection_reason',
        'commission_rate',
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function vendorProducts(): HasMany
    {
        return $this->hasMany(VendorProduct::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function orderMappings(): HasMany
    {
        return $this->hasMany(VendorOrderMapping::class);
    }

    public function approvalLogs(): HasMany
    {
        return $this->hasMany(VendorApprovalLog::class)->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class);
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_vendor')->withTimestamps();
    }

    public function vendorTypes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(VendorType::class, 'vendor_vendor_type')->withTimestamps();
    }

    public function partnerships(): HasMany
    {
        return $this->hasMany(VendorPartnership::class);
    }

    public function partnershipApplications(): HasMany
    {
        return $this->hasMany(VendorPartnershipApplication::class);
    }

    public function activePartnership(): HasOne
    {
        return $this->hasOne(VendorPartnership::class)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->latest('starts_at');
    }

    public function statusEnum(): VendorStatus
    {
        return VendorStatus::tryFrom($this->status) ?? VendorStatus::Pending;
    }

    public function isApproved(): bool
    {
        return $this->status === VendorStatus::Approved->value;
    }

    public function scopeApproved($query)
    {
        return $query->where('status', VendorStatus::Approved->value);
    }

    public function scopeStatus($query, ?string $status)
    {
        if ($status) {
            $query->where('status', $status);
        }

        return $query;
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = $this->profile?->logo_path;
        if (empty($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return request()->getSchemeAndHttpHost()
            ? rtrim(request()->getSchemeAndHttpHost(), '/').'/media/'.$path
            : asset('media/'.$path);
    }
}
