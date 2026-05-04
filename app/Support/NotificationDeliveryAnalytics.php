<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates database notification rows by Laravel notification class and recipient audience_role.
 */
final class NotificationDeliveryAnalytics
{
    private static function audienceRoleSqlExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "coalesce(nullif((data::json->>'audience_role'), ''), 'untracked')",
            'sqlite' => "coalesce(json_extract(data, '$.audience_role'), 'untracked')",
            default => "coalesce(json_unquote(json_extract(data, '$.audience_role')), 'untracked')",
        };
    }

    /**
     * @return array{
     *   since:?string,
     *   until:?string,
     *   grand_total:int,
     *   by_audience: array<string,int>,
     *   by_notification_type: array<int, array{
     *     notification_type: string,
     *     notification_type_short: string,
     *     total_deliveries: int,
     *     by_audience: array<string,int>
     *   }>
     * }
     */
    public static function aggregate(?string $since, ?string $until): array
    {
        $audExpr = self::audienceRoleSqlExpression();

        $base = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->when($since !== null && $since !== '', fn ($q) => $q->whereDate('created_at', '>=', $since))
            ->when($until !== null && $until !== '', fn ($q) => $q->whereDate('created_at', '<=', $until));

        $rows = (clone $base)
            ->selectRaw("type, {$audExpr} as audience_role, COUNT(*) as cnt")
            ->groupByRaw("type, {$audExpr}")
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
            'grand_total' => $grandTotal,
            'by_audience' => $summaryAudience,
            'by_audience_labeled' => $byAudienceLabeled,
            'by_notification_type' => array_values($byType),
        ];
    }
}
