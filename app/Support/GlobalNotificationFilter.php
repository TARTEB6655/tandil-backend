<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ReportGeneratedNotification;
use Illuminate\Notifications\DatabaseNotification;

class GlobalNotificationFilter
{
    public static function apply($query)
    {
        return $query->where('type', '!=', ReportGeneratedNotification::class);
    }

    /**
     * All stored User notifications in the system (admin cross-role inbox / delivery review).
     * Not restricted to the current user as notifiable.
     *
     * @return \Illuminate\Database\Eloquent\Builder<DatabaseNotification>
     */
    public static function allUsers(?string $audienceRole = null)
    {
        $q = DatabaseNotification::query()->where('notifiable_type', User::class);
        $q = self::apply($q);

        return self::applyAudienceRoleFilter($q, $audienceRole);
    }

    /**
     * Unread subset of {@see self::allUsers()}.
     *
     * @return \Illuminate\Database\Eloquent\Builder<DatabaseNotification>
     */
    public static function unreadAllUsers(?string $audienceRole = null)
    {
        $q = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereNull('read_at');
        $q = self::apply($q);

        return self::applyAudienceRoleFilter($q, $audienceRole);
    }

    /**
     * Single notification row for admin moderation (any recipient user).
     */
    public static function findForAdminReview(string $id): ?DatabaseNotification
    {
        $q = DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->whereKey($id);

        return self::apply($q)->first();
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

