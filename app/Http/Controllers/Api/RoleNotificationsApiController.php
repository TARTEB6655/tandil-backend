<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\GlobalNotificationFilter;
use App\Support\NotificationInboxWebFilters;
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
        $filter = (string) $request->query('filter', 'all');
        $kind = $request->query('kind');
        $search = trim((string) $request->query('q', ''));

        // Admin dedicated API behaves like "Statics" page: all users + full filter set.
        $isAdminStatsApi = $request->is('api/admin/notifications') && $user->hasRole('admin');
        $base = $isAdminStatsApi
            ? GlobalNotificationFilter::allUsers($audienceRole)
            : UserNotificationInbox::forUser($user, $audienceRole);

        $scoped = NotificationInboxWebFilters::applyKind($base, is_string($kind) ? $kind : null);
        if ($search !== '') {
            $scoped->where('data', 'like', '%' . $search . '%');
        }

        $listQuery = clone $scoped;
        if ($filter === 'unread') {
            $listQuery->whereNull('read_at');
        } elseif ($filter === 'read') {
            $listQuery->whereNotNull('read_at');
        }

        $paginator = $listQuery->latest()->paginate($perPage);
        $unreadCount = (clone $scoped)->whereNull('read_at')->count();
        $totalCount = (clone $scoped)->count();

        // Same dual shape as GET /api/user/notifications: legacy flat paginator keys + notifications + unread_count.
        $payload = array_merge($paginator->toArray(), [
            'unread_count' => $unreadCount,
            'total_count' => $totalCount,
            'read_count' => max(0, $totalCount - $unreadCount),
            'scope' => $isAdminStatsApi ? 'all_users' : 'self',
            'applied_filters' => [
                'q' => $search !== '' ? $search : null,
                'filter' => in_array($filter, ['all', 'unread', 'read'], true) ? $filter : 'all',
                'kind' => is_string($kind) ? ($kind !== '' ? $kind : 'all') : 'all',
                'audience_role' => is_string($audienceRole) && $audienceRole !== '' ? $audienceRole : null,
            ],
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
