<?php

namespace App\Enums;

enum VendorPartnershipStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case PendingPayment = 'pending_payment';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::PendingPayment => 'Pending Payment',
        };
    }
}
