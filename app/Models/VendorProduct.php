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
        'disabled_by_admin',
        'disabled_by_admin_at',
        'disabled_by_admin_by',
        'admin_disable_reason',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'disabled_by_admin' => 'boolean',
        'disabled_by_admin_at' => 'datetime',
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

    public function disabledByAdminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by_admin_by');
    }

    public function displayStatusKey(): string
    {
        if ($this->disabled_by_admin) {
            return 'disabled_by_admin';
        }

        $stock = $this->inventory?->quantity ?? 0;
        if ($stock <= 0 && $this->status === 'active' && $this->approval_status === 'approved') {
            return 'out_of_stock';
        }

        if (($this->product?->status ?? '') === 'draft') {
            return 'draft';
        }

        if ($this->approval_status === 'pending') {
            return 'pending_review';
        }

        if ($this->approval_status === 'rejected') {
            return 'rejected';
        }

        if ($this->approval_status === 'approved' && $this->status === 'active') {
            return 'active';
        }

        if ($this->status === 'inactive') {
            return 'inactive';
        }

        return $this->status ?? 'unknown';
    }

    public function displayStatusLabel(): string
    {
        return match ($this->displayStatusKey()) {
            'disabled_by_admin' => 'Disabled by Admin',
            'out_of_stock' => 'Out of Stock',
            'draft' => 'Draft',
            'pending_review' => 'Pending Review',
            'rejected' => 'Rejected',
            'active' => 'Active',
            'inactive' => 'Inactive',
            default => ucfirst(str_replace('_', ' ', $this->displayStatusKey())),
        };
    }

    public function isMarketplaceVisible(): bool
    {
        return $this->status === 'active'
            && $this->approval_status === 'approved'
            && ! $this->disabled_by_admin
            && ($this->product?->status ?? '') === 'active';
    }
}
