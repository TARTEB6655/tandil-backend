<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Support\GlobalNotificationFilter;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * List user notifications
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $notifications = GlobalNotificationFilter::forUser($user)
            ->latest()
            ->paginate(20);

        $unreadCount = GlobalNotificationFilter::unreadForUser($user)->count();

        return ApiResponse::success('Notifications retrieved successfully.', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        $notification = GlobalNotificationFilter::forUser($user)->find($id);

        if (!$notification) {
            return ApiResponse::error('Notification not found. Make sure you are using the correct notification UUID from GET /api/notifications response.', 404);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        GlobalNotificationFilter::unreadForUser($user)->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }

    /**
     * Delete one notification for authenticated user.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $notification = GlobalNotificationFilter::forUser($user)->find($id);

        if (! $notification) {
            return ApiResponse::error('Notification not found. Make sure you are using the correct notification UUID from GET /api/notifications response.', 404);
        }

        $notification->delete();

        return ApiResponse::success('Notification deleted successfully.');
    }

    /**
     * Delete all notifications for authenticated user.
     */
    public function clearAll(Request $request)
    {
        $user = $request->user();
        $query = GlobalNotificationFilter::forUser($user);
        $deleted = $query->count();
        $query->delete();

        return ApiResponse::success('All notifications cleared successfully.', [
            'deleted_count' => $deleted,
        ]);
    }
}
