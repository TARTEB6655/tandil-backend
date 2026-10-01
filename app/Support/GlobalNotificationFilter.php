<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ReportGeneratedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class GlobalNotificationFilter
{
    /**
     * Resolved audience role from stored JSON: top-level audience_role, then meta.audience_role, else untracked.
     */
    public static function resolvedAudienceRoleSqlExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "coalesce(
                nullif((data::json->>'audience_role'), ''),
                nullif((data::json->'meta'->>'audience_role'), ''),
                'untracked'
            )",
            'sqlite' => "coalesce(
                nullif(json_extract(data, '$.audience_role'), ''),
                nullif(json_extract(data, '$.meta.audience_role'), ''),
                'untracked'
            )",
            default => "coalesce(
                nullif(json_unquote(json_extract(data, '$.audience_role')), ''),
                nullif(json_unquote(json_extract(data, '$.meta.audience_role')), ''),
                'untracked'
            )",
        };
    }

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

        $expr = self::resolvedAudienceRoleSqlExpression();

        // other / unknown must stay JSON-only (no Spatie bucket).
        if (! in_array($audienceRole, UserNotificationAudience::PRIORITY_ROLES, true)) {
            return $query->whereRaw("({$expr}) = ?", [$audienceRole]);
        }

        /*
         * Primary: stored audience_role (top-level or meta) matches.
         * Fallback: older rows resolve to "untracked" because payload never had audience merged — match recipient
         * user's Spatie role (same keys as filter) so admin role filters are not empty for legacy data.
         */
        return $query->where(function ($group) use ($expr, $audienceRole) {
            $role = strtolower($audienceRole);
            $group->whereRaw("({$expr}) = ?", [$audienceRole])
                ->orWhere(function ($legacy) use ($expr, $role) {
                    $legacy->whereRaw("({$expr}) = ?", ['untracked'])
                        ->where('notifiable_type', User::class)
                        ->whereIn('notifiable_id', User::query()
                            ->select('id')
                            ->where(function ($userQuery) use ($role) {
                                $userQuery->whereRaw('LOWER(role) = ?', [$role])
                                    ->orWhereHas('roles', fn ($roles) => $roles->whereRaw('LOWER(name) = ?', [$role]));
                            }));
                });
        });
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

