<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates database notification rows by Laravel notification class and recipient audience_role.
 */
final class NotificationDeliveryAnalytics
{
    /**
     * Must match how {@see GlobalNotificationFilter} resolves audience (top-level + meta), or all rows count as untracked.
     */
    private static function audienceRoleSqlExpression(): string
    {
        return GlobalNotificationFilter::resolvedAudienceRoleSqlExpression();
    }

    /**
     * Same row scope as admin inbox / delivery API (optionally filtered by recipient audience).
     *
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Notifications\DatabaseNotification>
     */
    private static function analyticsBase(?string $since, ?string $until, ?string $audienceRole)
    {
        $q = GlobalNotificationFilter::allUsers($audienceRole);
        if ($since !== null && $since !== '') {
            $q->whereDate('notifications.created_at', '>=', $since);
        }
        if ($until !== null && $until !== '') {
            $q->whereDate('notifications.created_at', '<=', $until);
        }

        return $q;
    }

    /**
     * @return array{
     *   since:?string,
     *   until:?string,
     *   audience_role:?string,
     *   grand_total:int,
     *   tracking: array{tracked:int, untracked:int},
     *   by_audience: array<string,int>,
     *   by_audience_labeled: array<string,int>,
     *   by_notification_type: array<int, array{
     *     notification_type: string,
     *     notification_type_short: string,
     *     total_deliveries: int,
     *     by_audience: array<string,int>
     *   }>
     * }
     */
    public static function aggregate(?string $since, ?string $until, ?string $audienceRole = null): array
    {
        $audExpr = self::audienceRoleSqlExpression();
        $base = self::analyticsBase($since, $until, $audienceRole);

        $perRow = (clone $base)->selectRaw("type, {$audExpr} as audience_role");

        $rows = DB::query()
            ->fromSub($perRow->toBase(), 'n')
            ->selectRaw('n.type, n.audience_role, COUNT(*) as cnt')
            ->groupBy('n.type', 'n.audience_role')
            ->get();

        $byType = [];
        $summaryAudience = [];

        foreach (UserNotificationAudience::PRIORITY_ROLES as $role) {
            $summaryAudience[$role] = 0;
        }
        $summaryAudience['other'] = 0;
        $summaryAudience['unknown'] = 0;
        $summaryAudience['untracked'] = 0;

        foreach ($rows as $row) {
            $type = (string) $row->type;
            $aud = (string) $row->audience_role;
            $cnt = (int) $row->cnt;

            if (! isset($byType[$type])) {
                $byType[$type] = [
                    'notification_type' => $type,
                    'notification_type_short' => class_basename($type),
                    'total_deliveries' => 0,
                    'by_audience' => array_fill_keys(array_merge(UserNotificationAudience::PRIORITY_ROLES, ['other', 'unknown', 'untracked']), 0),
                ];
            }

            $byType[$type]['total_deliveries'] += $cnt;
            if (! isset($byType[$type]['by_audience'][$aud])) {
                $byType[$type]['by_audience'][$aud] = 0;
            }
            $byType[$type]['by_audience'][$aud] += $cnt;

            if (! isset($summaryAudience[$aud])) {
                $summaryAudience[$aud] = 0;
            }
            $summaryAudience[$aud] += $cnt;
        }

        $grandTotal = (int) (clone $base)->count();

        $tracked = (int) (clone $base)->whereRaw("({$audExpr}) != ?", ['untracked'])->count();
        $untracked = (int) (clone $base)->whereRaw("({$audExpr}) = ?", ['untracked'])->count();

        $byAudienceLabeled = [
            'customers' => (int) ($summaryAudience['client'] ?? 0),
            'technicians' => (int) ($summaryAudience['technician'] ?? 0),
            'supervisors' => (int) ($summaryAudience['supervisor'] ?? 0),
            'area_managers' => (int) ($summaryAudience['area_manager'] ?? 0),
            'hr' => (int) ($summaryAudience['hr'] ?? 0),
            'admins' => (int) ($summaryAudience['admin'] ?? 0),
            'other' => (int) ($summaryAudience['other'] ?? 0),
            'unknown' => (int) ($summaryAudience['unknown'] ?? 0),
            'untracked' => (int) ($summaryAudience['untracked'] ?? 0),
        ];

        return [
            'since' => $since,
            'until' => $until,
            'audience_role' => $audienceRole !== null && $audienceRole !== '' ? $audienceRole : null,
            'grand_total' => $grandTotal,
            'tracking' => [
                'tracked' => $tracked,
                'untracked' => $untracked,
            ],
            'by_audience' => $summaryAudience,
            'by_audience_labeled' => $byAudienceLabeled,
            'by_notification_type' => array_values($byType),
        ];
    }
}
