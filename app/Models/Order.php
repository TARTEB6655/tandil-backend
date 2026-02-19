<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'guest_email',
        'guest_full_name',
        'guest_phone',
        'guest_street_address',
        'guest_city',
        'guest_state',
        'guest_zip_code',
        'guest_country',
        'package_id',
        'shipping_address_id',
        'total_amount',
        'shipping_amount',
        'payment_status',
        'payment_reference',
        'payment_method',
        'transaction_id',
        'paid_at',
        'order_status',
        'refunded_at',
        'refund_amount',
        'refund_reason',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Shipping address for this order.
     */
    public function shippingAddress()
    {
        return $this->belongsTo(UserAddress::class, 'shipping_address_id');
    }

    /**
     * Get the user who placed this order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package (when order is a package order).
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get all items in this order.
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get all transactions for this order.
     */
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactionable');
    }

    /**
     * Shipping address for API (logged-in: from user_addresses; guest: from guest_* columns).
     */
    public function getShippingAddressForApi(): ?array
    {
        if ($this->shippingAddress) {
            return $this->shippingAddress->toApiArray();
        }
        if ($this->guest_full_name || $this->guest_email) {
            return [
                'full_name' => $this->guest_full_name,
                'phone_number' => $this->guest_phone,
                'street_address' => $this->guest_street_address,
                'city' => $this->guest_city,
                'state' => $this->guest_state,
                'zip_code' => $this->guest_zip_code,
                'country' => $this->guest_country,
            ];
        }
        return null;
    }

    public function isGuestOrder(): bool
    {
        return $this->user_id === null;
    }
}
