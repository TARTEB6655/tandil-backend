<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\AreaManagerNotificationFilter;
use Illuminate\Http\Request;

class AreaManagerNotificationsApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;

        $notifications = AreaManagerNotificationFilter::forUser($user)
            ->latest()
            ->paginate($perPage);

        $unreadCount = AreaManagerNotificationFilter::unreadForUser($user)->count();

        return ApiResponse::success('Area manager notifications retrieved successfully.', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = $request->user();
        $notification = AreaManagerNotificationFilter::forUser($user)->find($id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        AreaManagerNotificationFilter::unreadForUser($user)->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $notification = AreaManagerNotificationFilter::forUser($user)->find($id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->delete();

        return ApiResponse::success('Notification deleted successfully.');
    }

    public function clearAll(Request $request)
    {
        $user = $request->user();
        $query = AreaManagerNotificationFilter::forUser($user);
        $deleted = $query->count();
        $query->delete();

        return ApiResponse::success('All notifications cleared successfully.', [
            'deleted_count' => $deleted,
        ]);
    }
}
