<?php

namespace App\Support;

use App\Models\User;

/**
 * Resolves the correct notification query for the authenticated user (role-aware inbox).
 */
final class UserNotificationInbox
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany|\Illuminate\Database\Eloquent\Builder
     */
    public static function forUser(User $user, ?string $audienceRole = null)
    {
        if ($user->hasRole('hr')) {
            return HrNotificationFilter::forUser($user, $audienceRole);
        }
        if ($user->hasRole('area_manager')) {
            return AreaManagerNotificationFilter::forUser($user, $audienceRole);
        }

        return GlobalNotificationFilter::forUser($user, $audienceRole);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany|\Illuminate\Database\Eloquent\Builder
     */
    public static function unreadForUser(User $user, ?string $audienceRole = null)
    {
        if ($user->hasRole('hr')) {
            return HrNotificationFilter::unreadForUser($user, $audienceRole);
        }
        if ($user->hasRole('area_manager')) {
            return AreaManagerNotificationFilter::unreadForUser($user, $audienceRole);
        }

        return GlobalNotificationFilter::unreadForUser($user, $audienceRole);
    }
}
