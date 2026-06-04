<?php

namespace App\Models;

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
        'address',
        'tax_vat_number',
        'logo_path',
        'description',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
