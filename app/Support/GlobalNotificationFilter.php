<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ReportGeneratedNotification;

class GlobalNotificationFilter
{
    public static function apply($query)
    {
        return $query->where('type', '!=', ReportGeneratedNotification::class);
    }

    public static function applyAudienceRoleFilter($query, ?string $audienceRole)
    {
        if ($audienceRole === null || $audienceRole === '') {
            return $query;
        }
        $allowed = array_merge(UserNotificationAudience::PRIORITY_ROLES, ['other', 'unknown']);
        if (! in_array($audienceRole, $allowed, true)) {
            return $query;
        }
        $safe = str_replace(['%', '_'], ['\\%', '\\_'], $audienceRole);

        return $query->where('data', 'like', '%"audience_role":"' . $safe . '"%');
    }

    public static function forUser(User $user, ?string $audienceRole = null)
    {
        $q = self::apply($user->notifications());

        return self::applyAudienceRoleFilter($q, $audienceRole);
    }

    public static function unreadForUser(User $user, ?string $audienceRole = null)
    {
        $q = self::apply($user->unreadNotifications());

        return self::applyAudienceRoleFilter($q, $audienceRole);
    }
}

