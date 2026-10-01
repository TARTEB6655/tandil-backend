<?php

namespace App\Support;

use App\Notifications\AdminNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\TipPublishedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Web inbox filters aligned with client feedback: group by notification type (role-appropriate content)
 * and optional audience_role for admin review.
 */
final class NotificationInboxWebFilters
{
    public const KIND_ALL = 'all';

    public const KIND_ANNOUNCEMENT = 'announcement';

    public const KIND_TIP = 'tip';

    public const KIND_LEAVE = 'leave';

    public const KIND_OTHER = 'other';

    /** @return list<string> */
    public static function allowedKinds(): array
    {
        return [
            self::KIND_ALL,
            self::KIND_ANNOUNCEMENT,
            self::KIND_TIP,
            self::KIND_LEAVE,
            self::KIND_OTHER,
        ];
    }

    /**
     * @param  Builder<\Illuminate\Notifications\DatabaseNotification>|Relation  $query
     * @return Builder<\Illuminate\Notifications\DatabaseNotification>
     */
    public static function applyKind(Builder|Relation $query, ?string $kind): Builder
    {
        if ($query instanceof Relation) {
            $query = $query->getQuery();
        }

        $kind = $kind ?? self::KIND_ALL;
        if ($kind === self::KIND_ALL || ! in_array($kind, self::allowedKinds(), true)) {
            return $query;
        }

        $known = [
            AdminNotification::class,
            TipPublishedNotification::class,
            LeaveRequestStatusNotification::class,
        ];

        if ($kind === self::KIND_ANNOUNCEMENT) {
            return $query->where('type', AdminNotification::class);
        }
        if ($kind === self::KIND_TIP) {
            return $query->where('type', TipPublishedNotification::class);
        }
        if ($kind === self::KIND_LEAVE) {
            return $query->where('type', LeaveRequestStatusNotification::class);
        }
        if ($kind === self::KIND_OTHER) {
            return $query->whereNotIn('type', $known);
        }

        return $query;
    }

    public static function kindLabel(string $kind): string
    {
        return match ($kind) {
            self::KIND_ANNOUNCEMENT => 'Announcements',
            self::KIND_TIP => 'Tips',
            self::KIND_LEAVE => 'Leave & HR',
            self::KIND_OTHER => 'Other',
            default => 'All types',
        };
    }

    /** @return array<string, string> kind => label */
    public static function kindOptions(): array
    {
        return [
            self::KIND_ALL => 'All types',
            self::KIND_ANNOUNCEMENT => 'Announcements',
            self::KIND_TIP => 'Tips',
            self::KIND_LEAVE => 'Leave & HR',
            self::KIND_OTHER => 'Other',
        ];
    }
}
