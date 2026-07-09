<?php

namespace App\Models;

use App\Enums\VendorPartnershipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPartnership extends Model
{
    protected $fillable = [
        'vendor_id',
        'tier_id',
        'status',
        'starts_at',
        'ends_at',
        'next_payment_at',
        'payment_method',
        'estimated_products',
        'business_description',
        'contact_phone',
        'assigned_by',
        'application_id',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_payment_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(VendorPartnershipTier::class, 'tier_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(VendorPartnershipApplication::class, 'application_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== VendorPartnershipStatus::Active->value) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function daysRemaining(): int
    {
        if ($this->ends_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->ends_at, false));
    }
}
