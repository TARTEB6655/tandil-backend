<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorProductPrice extends Model
{
    protected $fillable = [
        'vendor_product_id',
        'price',
        'compare_at_price',
        'currency',
        'effective_from',
        'effective_to',
        'set_by_user_id',
        'is_admin_override',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_admin_override' => 'boolean',
    ];

    public function vendorProduct(): BelongsTo
    {
        return $this->belongsTo(VendorProduct::class);
    }

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
