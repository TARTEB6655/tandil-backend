<?php

namespace App\Models;

use App\Enums\VendorPartnershipApplicationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPartnershipApplication extends Model
{
    protected $fillable = [
        'vendor_id',
        'tier_id',
        'type',
        'estimated_products',
        'business_description',
        'contact_phone',
        'payment_method',
        'status',
        'admin_notes',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(VendorPartnershipTier::class, 'tier_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === VendorPartnershipApplicationStatus::Pending->value;
    }
}
