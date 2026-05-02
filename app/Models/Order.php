<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'subtotal_amount',
        'tax_amount',
        'tax_percent',
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
        'special_instructions',
        'estimated_arrival',
        'job_duration',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
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

    /**
     * Zero-padded numeric segment (min 4 chars) for display and API order_number suffix.
     */
    public function publicOrderNumberDigits(): string
    {
        $width = max(4, strlen((string) $this->id));

        return str_pad((string) $this->id, $width, '0', STR_PAD_LEFT);
    }

    /**
     * Stable public reference (e.g. order_0006). Unique per order; guest lookup parses digits after "order_".
     */
    public function publicOrderNumber(): string
    {
        return 'order_'.$this->publicOrderNumberDigits();
    }

    public function isShopOrder(): bool
    {
        return $this->package_id === null;
    }

    public function payerDisplayName(): string
    {
        if ($this->isGuestOrder()) {
            return (string) ($this->guest_full_name ?? 'Guest');
        }
        if ($this->shippingAddress && $this->shippingAddress->full_name) {
            return (string) $this->shippingAddress->full_name;
        }

        return (string) ($this->user?->name ?? 'Customer');
    }

    public function payerEmail(): ?string
    {
        if ($this->isGuestOrder()) {
            return $this->guest_email;
        }

        return $this->user?->email;
    }

    public function payerPhone(): ?string
    {
        if ($this->isGuestOrder()) {
            return $this->guest_phone;
        }

        return $this->shippingAddress?->phone_number ?: $this->user?->phone;
    }

    public function payerAddressForDisplay(): string
    {
        if ($this->isGuestOrder()) {
            $lines = array_filter([
                $this->guest_street_address,
                trim(implode(' ', array_filter([$this->guest_city, $this->guest_state, $this->guest_zip_code]))),
                $this->guest_country,
            ]);

            return implode("\n", $lines);
        }
        if ($this->shippingAddress) {
            $a = $this->shippingAddress;
            $lines = array_filter([
                $a->street_address,
                trim(implode(' ', array_filter([$a->city, $a->state, $a->zip_code]))),
                $a->country,
            ]);

            return implode("\n", $lines);
        }

        return '';
    }

    /**
     * Short line for tables (Stripe-style description).
     */
    public function paymentActivityDescription(): string
    {
        $name = $this->payerDisplayName();
        if ($this->isGuestOrder()) {
            $city = (string) ($this->guest_city ?? '');
            $country = (string) ($this->guest_country ?? '');
            $zip = (string) ($this->guest_zip_code ?? '');
        } else {
            $city = (string) ($this->shippingAddress?->city ?? '');
            $country = (string) ($this->shippingAddress?->country ?? '');
            $zip = (string) ($this->shippingAddress?->zip_code ?? '');
        }

        return implode(' - ', array_filter(['Shop', $name, $city, $country, $zip], fn ($p) => $p !== ''));
    }

    public function paymentMethodLabel(): string
    {
        $m = strtolower((string) ($this->payment_method ?? ''));

        return match ($m) {
            'stripe' => 'Stripe',
            'paypal' => 'PayPal',
            default => $m !== '' ? ucfirst($m) : '—',
        };
    }
}
