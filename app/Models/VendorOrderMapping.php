<?php

namespace App\Models;

use App\Enums\VendorOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorOrderMapping extends Model
{
    protected $fillable = [
        'order_id',
        'vendor_id',
        'status',
        'tracking_number',
        'delivery_otp',
        'delivery_otp_expires_at',
        'delivery_otp_confirmed_at',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'total_amount',
        'commission_amount',
        'dispute_status',
        'dispute_notes',
        'admin_notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'vendor_notified_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'cancelled_at' => 'datetime',
        'vendor_notified_at' => 'datetime',
        'delivery_otp_expires_at' => 'datetime',
        'delivery_otp_confirmed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(VendorOrderStatusLog::class)->orderBy('created_at');
    }

    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function statusEnum(): VendorOrderStatus
    {
        return VendorOrderStatus::tryFrom($this->status) ?? VendorOrderStatus::Pending;
    }
}
