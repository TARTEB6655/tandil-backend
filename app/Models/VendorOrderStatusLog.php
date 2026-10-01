<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorOrderStatusLog extends Model
{
    protected $fillable = [
        'vendor_order_mapping_id',
        'status',
        'changed_by',
        'note',
    ];

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(VendorOrderMapping::class, 'vendor_order_mapping_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
