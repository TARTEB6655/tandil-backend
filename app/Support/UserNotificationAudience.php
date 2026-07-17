<?php

namespace App\Support;

use App\Models\User;

/**
 * Single audience bucket per user for notifications and broadcast statistics.
 * Priority order picks one role when a user has multiple Spatie roles.
 */
final class UserNotificationAudience
{
    public const PRIORITY_ROLES = [
        'client',
        'technician',
        'supervisor',
        'area_manager',
        'hr',
        'vendor',
        'admin',
    ];

    public static function resolve(User $user): string
    {
        foreach (self::PRIORITY_ROLES as $role) {
            if ($user->hasRole($role)) {
                return $role;
            }
        }

        $legacy = strtolower((string) ($user->role ?? ''));
        if (in_array($legacy, self::PRIORITY_ROLES, true)) {
            return $legacy;
        }

        if ($legacy !== '') {
            return 'other';
        }

        return 'unknown';
    }

    /** Stat column prefix on admin_notification_broadcasts (snake). */
    public static function countColumn(string $audience): string
    {
        return match ($audience) {
            'client' => 'recipient_client_count',
            'technician' => 'recipient_technician_count',
            'supervisor' => 'recipient_supervisor_count',
            'area_manager' => 'recipient_area_manager_count',
            'hr' => 'recipient_hr_count',
            'vendor' => 'recipient_vendor_count',
            'admin' => 'recipient_admin_count',
            default => 'recipient_other_count',
        };
    }

    /** Human-facing labels for admin UI / API. */
    public static function labels(): array
    {
        return [
            'client' => 'Customers',
            'technician' => 'Technicians',
            'supervisor' => 'Supervisors',
            'area_manager' => 'Area managers',
            'hr' => 'HR',
            'vendor' => 'Vendors',
            'admin' => 'Admins',
            'other' => 'Other',
            'unknown' => 'Unknown',
        ];
    }
}
