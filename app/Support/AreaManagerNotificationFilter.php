<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ReportGeneratedNotification;

class AreaManagerNotificationFilter
{
    public static function apply($query)
    {
        return $query->where('type', '!=', ReportGeneratedNotification::class);
    }

    public static function forUser(User $user, ?string $audienceRole = null)
    {
        $q = self::apply($user->notifications());

        return GlobalNotificationFilter::applyAudienceRoleFilter($q, $audienceRole);
    }

    public static function unreadForUser(User $user, ?string $audienceRole = null)
    {
        $q = self::apply($user->unreadNotifications());

        return GlobalNotificationFilter::applyAudienceRoleFilter($q, $audienceRole);
    }
}

