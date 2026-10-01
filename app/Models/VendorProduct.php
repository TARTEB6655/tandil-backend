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

    /**
     * Canonical on-hand quantity for vendor listings.
     * Prefer vendor_inventory; fall back to products.stock for legacy rows.
     */
    public function stockQuantity(): int
    {
        $this->loadMissing(['inventory', 'product']);

        if ($this->inventory) {
            return max(0, (int) $this->inventory->quantity);
        }

        return max(0, (int) ($this->product?->stock ?? 0));
    }

    public function lowStockThreshold(): int
    {
        $this->loadMissing('inventory');

        return max(0, (int) ($this->inventory?->low_stock_threshold ?? 5));
    }

    public function isOutOfStock(): bool
    {
        return $this->stockQuantity() <= 0;
    }

    public function isLowStock(): bool
    {
        $quantity = $this->stockQuantity();

        return $quantity > 0 && $quantity <= $this->lowStockThreshold();
    }

    public function displayStatusKey(): string
    {
        if ($this->disabled_by_admin) {
            return 'disabled_by_admin';
        }

        $stock = $this->stockQuantity();
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

    public function isAdminLive(): bool
    {
        return $this->isMarketplaceVisible();
    }

    /**
     * Products that are live on the customer marketplace.
     */
    public function scopeMarketplaceLive($query)
    {
        return $query
            ->where('vendor_products.status', 'active')
            ->where('vendor_products.approval_status', 'approved')
            ->where('vendor_products.disabled_by_admin', false)
            ->whereHas('product', fn ($q) => $q->where('status', 'active'));
    }

    /**
     * Resolve a vendor listing by vendor_product id or underlying product id.
     */
    public static function findForVendorToggle(int $vendorId, int $productId): self
    {
        return static::query()
            ->where('vendor_id', $vendorId)
            ->where(function ($query) use ($productId) {
                $query->where('id', $productId)
                    ->orWhere('product_id', $productId);
            })
            ->firstOrFail();
    }
}
