<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Notifications\AdminNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Support\CapsPagination;
use App\Support\GlobalNotificationFilter;
use App\Support\NotificationInboxWebFilters;
use App\Support\NotificationSearch;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;

class RoleNotificationsApiController extends Controller
{
    private function isAdminStatsApi(Request $request): bool
    {
        if (! $request->user()?->hasRole('admin')) {
            return false;
        }

        $path = $request->path();

        if ($path === 'api/admin/notifications') {
            return true;
        }

        if (! str_starts_with($path, 'api/admin/notifications/')) {
            return false;
        }

        $suffix = substr($path, strlen('api/admin/notifications/'));

        return ! str_starts_with($suffix, 'broadcast')
            && ! str_starts_with($suffix, 'delivery-stats');
    }

    /**
     * Same row scope as GET index (self inbox vs admin all-users statistics).
     */
    private function buildScopedQuery(Request $request)
    {
        $user = $request->user();
        $audienceRole = $request->query('audience_role');
        $filter = (string) $request->query('filter', 'all');
        $kindRaw = $request->query('kind');
        $kind = is_string($kindRaw) ? trim($kindRaw) : null;
        if ($kind === '') {
            $kind = null;
        }
        $search = trim((string) $request->query('q', ''));

        $base = $this->isAdminStatsApi($request)
            ? GlobalNotificationFilter::allUsers($audienceRole)
            : UserNotificationInbox::forUser($user, $audienceRole);

        $expandLeaveWithBroadcasts = ! $this->isAdminStatsApi($request)
            && $kind === NotificationInboxWebFilters::KIND_LEAVE
            && $user->hasAnyRole(['technician', 'supervisor', 'client']);

        if ($expandLeaveWithBroadcasts) {
            $scoped = $base->where(function ($q) {
                $q->where('type', LeaveRequestStatusNotification::class)
                    ->orWhere('type', AdminNotification::class);
            });
        } else {
            $scoped = NotificationInboxWebFilters::applyKind($base, is_string($kind) ? $kind : null);
        }

        if ($search !== '') {
            $scoped = NotificationSearch::apply($scoped, $search);
        }

        if ($filter === 'unread') {
            $scoped->whereNull('read_at');
        } elseif ($filter === 'read') {
            $scoped->whereNotNull('read_at');
        }

        return $scoped;
    }

    private function findForMutation(Request $request, string $id)
    {
        if ($this->isAdminStatsApi($request)) {
            return GlobalNotificationFilter::findForAdminReview($id);
        }

        return UserNotificationInbox::forUser($request->user())->find($id);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $perPage = CapsPagination::perPage($request, 20, 100);
        $audienceRole = $request->query('audience_role');
        $filter = (string) $request->query('filter', 'all');
        $kindRaw = $request->query('kind');
        $kind = is_string($kindRaw) ? trim($kindRaw) : null;
        if ($kind === '') {
            $kind = null;
        }
        $search = trim((string) $request->query('q', ''));

        // Admin dedicated API behaves like "Statics" page: all users + full filter set.
        $isAdminStatsApi = $this->isAdminStatsApi($request);
        $base = $isAdminStatsApi
            ? GlobalNotificationFilter::allUsers($audienceRole)
            : UserNotificationInbox::forUser($user, $audienceRole);

        /*
         * Mobile clients sometimes send kind=leave for the main notifications screen ("Leave & HR").
         * Include admin broadcasts in that filter for employee roles so role-targeted announcements appear
         * without requiring app updates. Admin cross-user stats API keeps strict leave-only filtering.
         */
        $expandLeaveWithBroadcasts = ! $isAdminStatsApi
            && $kind === NotificationInboxWebFilters::KIND_LEAVE
            && $user->hasAnyRole(['technician', 'supervisor', 'client']);

        if ($expandLeaveWithBroadcasts) {
            $scoped = $base->where(function ($q) {
                $q->where('type', LeaveRequestStatusNotification::class)
                    ->orWhere('type', AdminNotification::class);
            });
        } else {
            $scoped = NotificationInboxWebFilters::applyKind($base, is_string($kind) ? $kind : null);
        }
        if ($search !== '') {
            $scoped = NotificationSearch::apply($scoped, $search);
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
        $notification = $this->findForMutation($request, $id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->markAsRead();

        return ApiResponse::success('Notification marked as read.');
    }

    public function markAllAsRead(Request $request)
    {
        $this->buildScopedQuery($request)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return ApiResponse::success('All notifications marked as read.');
    }

    public function destroy(Request $request, string $id)
    {
        $notification = $this->findForMutation($request, $id);

        if (! $notification) {
            return ApiResponse::error('Notification not found.', 404);
        }

        $notification->delete();

        return ApiResponse::success('Notification deleted successfully.');
    }

    public function clearAll(Request $request)
    {
        $query = $this->buildScopedQuery($request);
        $deleted = $query->count();
        $query->delete();

        return ApiResponse::success('All notifications cleared successfully.', [
            'deleted_count' => $deleted,
        ]);
    }
}
