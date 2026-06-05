<?php

namespace App\Models;

use App\Enums\VendorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProfile extends Model
{
    protected $fillable = [
        'vendor_id',
        'business_name',
        'owner_name',
        'email',
        'phone',
        'trade_license_number',
        'vendor_type',
        'emirate',
        'city',
        'address',
        'google_maps_location',
        'bank_name',
        'iban',
        'account_holder_name',
        'delivery_radius',
        'operating_hours',
        'minimum_order_amount',
        'tax_vat_number',
        'logo_path',
        'description',
        'years_in_business',
        'terms_accepted_at',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'delivery_radius' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'terms_accepted_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    protected $appends = [
        'vendor_type_label',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getVendorTypeLabelAttribute(): ?string
    {
        if (empty($this->vendor_type)) {
            return null;
        }

        return VendorType::tryFrom($this->vendor_type)?->label() ?? ucfirst($this->vendor_type);
    }

    /** @return list<string> UAE emirates. */
    public static function emirates(): array
    {
        return [
            'Abu Dhabi',
            'Dubai',
            'Sharjah',
            'Ajman',
            'Umm Al Quwain',
            'Ras Al Khaimah',
            'Fujairah',
        ];
    }
}
