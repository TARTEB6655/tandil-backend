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

    public static function forUser(User $user)
    {
        return self::apply($user->notifications());
    }

    public static function unreadForUser(User $user)
    {
        return self::apply($user->unreadNotifications());
    }
}

