<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminNotification;
use App\Notifications\TipPublishedNotification;

class HrNotificationFilter
{
    public static function apply($query)
    {
        return $query->where(function ($outer) {
            $outer
                ->whereIn('type', [
                    TipPublishedNotification::class,
                ])
                ->orWhere('type', AdminNotification::class);
        });
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

