<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInventoryLog extends Model
{
    protected $fillable = [
        'vendor_product_id',
        'change_type',
        'quantity_before',
        'quantity_after',
        'changed_by',
        'notes',
    ];

    public function vendorProduct(): BelongsTo
    {
        return $this->belongsTo(VendorProduct::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
