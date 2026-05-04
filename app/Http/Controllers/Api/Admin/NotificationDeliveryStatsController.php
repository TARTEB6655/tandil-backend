<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\NotificationDeliveryAnalytics;
use Illuminate\Http\Request;

class NotificationDeliveryStatsController extends Controller
{
    /**
     * Aggregate delivery counts from the notifications table (all user inboxes), grouped by
     * notification class and recipient audience_role (customers, technicians, supervisors, etc.).
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'since' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d',
        ]);

        $payload = NotificationDeliveryAnalytics::aggregate(
            $validated['since'] ?? null,
            $validated['until'] ?? null
        );

        return ApiResponse::success('Notification delivery statistics retrieved successfully.', $payload);
    }
}
