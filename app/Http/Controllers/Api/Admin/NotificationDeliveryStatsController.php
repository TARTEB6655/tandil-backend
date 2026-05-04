<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\NotificationDeliveryAnalytics;
use App\Support\UserNotificationAudience;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationDeliveryStatsController extends Controller
{
    /**
     * Aggregate delivery counts from the notifications table (all user inboxes), grouped by
     * notification class and recipient audience_role (customers, technicians, supervisors, etc.).
     * Optional audience_role scopes the same way as the admin notifications inbox filter.
     */
    public function index(Request $request)
    {
        $allowedAudience = array_merge(UserNotificationAudience::PRIORITY_ROLES, ['other', 'unknown']);

        $validated = $request->validate([
            'since' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d',
            'audience_role' => ['nullable', 'string', Rule::in($allowedAudience)],
        ]);

        $audienceRole = $validated['audience_role'] ?? null;
        if ($audienceRole === '') {
            $audienceRole = null;
        }

        $payload = NotificationDeliveryAnalytics::aggregate(
            $validated['since'] ?? null,
            $validated['until'] ?? null,
            $audienceRole
        );

        return ApiResponse::success('Notification delivery statistics retrieved successfully.', $payload);
    }
}
