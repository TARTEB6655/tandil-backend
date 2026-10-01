<?php

namespace App\Enums;

enum VendorOrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Confirmed => 'check',
            self::Processing => 'gear',
            self::Shipped => 'truck',
            self::Delivered => 'check-circle',
            self::Cancelled => 'x',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gold',
            self::Confirmed => 'blue',
            self::Processing => 'blue',
            self::Shipped => 'green',
            self::Delivered => 'green',
            self::Cancelled => 'red',
        };
    }

    /**
     * Visible stepper / Update Status buttons on the vendor Order Details screen.
     *
     * @return list<self>
     */
    public static function workflow(): array
    {
        return [
            self::Pending,
            self::Confirmed,
            self::Processing,
            self::Shipped,
            self::Delivered,
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
