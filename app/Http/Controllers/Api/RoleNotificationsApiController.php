<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;

class RoleNotificationsApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $audienceRole = $request->query('audience_role');

        $paginator = UserNotificationInbox::forUser($user, $audienceRole)
            ->latest()
            ->paginate($perPage);

        $unreadCount = UserNotificationInbox::unreadForUser($user, $audienceRole)->count();

        // Same dual shape as GET /api/user/notifications: legacy flat paginator keys + notifications + unread_count.
        $payload = array_merge($paginator->toArray(), [
            'unread_count' => $unreadCount,
            'notifications' => $paginator,
        ]);

        return ApiResponse::success('Notifications retrieved successfully.', $payload);
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();
        $notification = UserNotificationInbox::forUser($user)->find($id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        UserNotificationInbox::unreadForUser($user)->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $notification = UserNotificationInbox::forUser($user)->find($id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->delete();

        return ApiResponse::success('Notification deleted successfully.');
    }

    public function clearAll(Request $request)
    {
        $user = $request->user();
        $query = UserNotificationInbox::forUser($user);
        $deleted = $query->count();
        $query->delete();

        return ApiResponse::success('All notifications cleared successfully.', [
            'deleted_count' => $deleted,
        ]);
    }
}
