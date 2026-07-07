<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    public const SHIPPING_BIKE = 'bike';

    public const SHIPPING_CAR = 'car';

    /** @deprecated Use SHIPPING_BIKE */
    public const DELIVERY_BIKE = self::SHIPPING_BIKE;

    /** @deprecated Use SHIPPING_CAR */
    public const DELIVERY_CAR = self::SHIPPING_CAR;

    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'is_active',
        'sort_order',
        'shipping_cost',
        'shipping_type',
        'tax_percentage',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'shipping_cost' => 'float',
        'tax_percentage' => 'float',
    ];

    /**
     * Order categories for display (drag-and-drop sort_order, then name as tie-breaker).
     */
    public function scopeForVendorCatalog($query, ?int $vendorId)
    {
        return $query->where(function ($q) use ($vendorId) {
            $q->whereNull('vendor_id');
            if ($vendorId !== null) {
                $q->orWhere('vendor_id', $vendorId);
            }
        });
    }

    /**
     * Admin-managed categories vendors may assign to products.
     */
    public function scopePlatformCatalog($query)
    {
        return $query->whereNull('vendor_id');
    }

    public function vendorAccount()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Next available sort_order value (places new categories at the end).
     */
    public static function nextSortOrder(): int
    {
        return ((int) static::max('sort_order')) + 1;
    }

    protected $appends = ['image_url', 'coming_soon'];

    /**
     * @return array<string, string>
     */
    public static function shippingTypeOptions(): array
    {
        return [
            self::SHIPPING_BIKE => 'Bike (Small Products)',
            self::SHIPPING_CAR => 'Car (Large Products)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function deliveryTypeOptions(): array
    {
        return self::shippingTypeOptions();
    }

    public static function normalizeShippingType(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtolower(trim((string) $value));

        return in_array($v, [self::SHIPPING_BIKE, self::SHIPPING_CAR], true) ? $v : null;
    }

    public static function normalizeDeliveryType(mixed $value): ?string
    {
        return self::normalizeShippingType($value);
    }

    public static function shippingTypeLabel(?string $type): string
    {
        return match ($type) {
            self::SHIPPING_BIKE => 'Bike delivery (small products)',
            self::SHIPPING_CAR => 'Car delivery (large products)',
            default => 'Standard delivery',
        };
    }

    public static function deliveryTypeLabel(?string $type): string
    {
        return self::shippingTypeLabel($type);
    }

    public static function shippingTypeShortLabel(?string $type): string
    {
        return match ($type) {
            self::SHIPPING_BIKE => 'Bike',
            self::SHIPPING_CAR => 'Car',
            default => 'Standard',
        };
    }

    public static function deliveryTypeShortLabel(?string $type): string
    {
        return self::shippingTypeShortLabel($type);
    }

    /** Legacy API / form field name. */
    public function getShippingAmountAttribute(): ?float
    {
        $cost = $this->attributes['shipping_cost'] ?? null;

        return $cost !== null && $cost !== '' ? (float) $cost : null;
    }

    public function setShippingAmountAttribute(mixed $value): void
    {
        $this->attributes['shipping_cost'] = $value === null || $value === ''
            ? null
            : round(max(0, (float) $value), 2);
    }

    /** Legacy API / form field name. */
    public function getDeliveryTypeAttribute(): ?string
    {
        return $this->attributes['shipping_type'] ?? null;
    }

    public function setDeliveryTypeAttribute(mixed $value): void
    {
        $this->attributes['shipping_type'] = self::normalizeShippingType($value);
    }

    /**
     * Resolved tax % for checkout (category value or shop default).
     */
    public function effectiveTaxPercentage(): float
    {
        if ($this->tax_percentage !== null && $this->tax_percentage !== '') {
            return round((float) $this->tax_percentage, 2);
        }

        return \App\Http\Controllers\Shop\CartController::getEffectiveTaxPercent();
    }

    public function getComingSoonAttribute(): bool
    {
        return isset($this->attributes['is_active']) && ! (bool) $this->attributes['is_active'];
    }

    public function getImageUrlAttribute(): ?string
    {
        $image = $this->attributes['image'] ?? null;
        if (empty($image) || ! is_string($image)) {
            return null;
        }
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://')) {
            return $image;
        }
        $path = ltrim(str_replace('\\', '/', $image), '/');
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/media/' . $path;
        }

        return asset('media/' . $path);
    }

    /**
     * @return array{shipping_cost: ?float, tax_percentage: ?float, shipping_amount: ?float}
     */
    public function shippingTaxConfigForApi(): array
    {
        $cost = $this->shipping_cost !== null ? round((float) $this->shipping_cost, 2) : null;

        return [
            'shipping_cost' => $cost,
            'tax_percentage' => $this->tax_percentage !== null
                ? round((float) $this->tax_percentage, 2)
                : null,
            'shipping_amount' => $cost,
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}
