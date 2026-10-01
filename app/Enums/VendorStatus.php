<?php

namespace App\Enums;

enum VendorStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::UnderReview => 'Under Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Suspended => 'Suspended',
            self::Disabled => 'Disabled',
        };
    }

    /** Statuses where vendor may complete onboarding (profile, documents). */
    public function canCompleteOnboarding(): bool
    {
        return in_array($this, [self::Pending, self::Rejected, self::UnderReview], true);
    }

    public function canSell(): bool
    {
        return $this === self::Approved;
    }

    /** Uppercase label for mobile admin widgets. */
    public function displayStatus(): string
    {
        return match ($this) {
            self::Pending => 'PENDING',
            self::UnderReview => 'UNDER REVIEW',
            self::Approved => 'APPROVED',
            self::Rejected => 'REJECTED',
            self::Suspended => 'SUSPENDED',
            self::Disabled => 'DISABLED',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
