<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendorProduct extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'product_id',
        'status',
        'approval_status',
        'rejection_reason',
        'approved_at',
        'approved_by',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(VendorProductPrice::class)->orderByDesc('effective_from');
    }

    public function currentPrice(): HasOne
    {
        return $this->hasOne(VendorProductPrice::class)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>', now());
            })
            ->latestOfMany('effective_from');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(VendorInventory::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(VendorInventoryLog::class)->orderByDesc('created_at');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
