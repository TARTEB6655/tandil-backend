<?php

namespace App\Support;

use App\Notifications\AdminNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\TipPublishedNotification;

/**
 * Labels for notification list rows (client-facing dashboards).
 */
final class NotificationWebPresenter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function kindBadge(?string $typeClass, array $data = []): string
    {
        $t = $typeClass ?? '';
        if ($t === TipPublishedNotification::class || ($data['type'] ?? '') === 'tip_published') {
            return 'Tip';
        }
        if ($t === LeaveRequestStatusNotification::class || ($data['type'] ?? '') === 'leave_request_status') {
            return 'Leave';
        }
        if ($t === AdminNotification::class || ($data['type'] ?? '') === 'admin_notification') {
            return 'Message';
        }

        return 'Update';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function audienceLabel(array $data): ?string
    {
        $role = $data['audience_role'] ?? ($data['meta']['audience_role'] ?? null);
        if (! is_string($role) || $role === '') {
            return null;
        }

        return UserNotificationAudience::labels()[$role] ?? ucfirst(str_replace('_', ' ', $role));
    }
}
