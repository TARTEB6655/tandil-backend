<?php

namespace App\Http\Controllers\Concerns;

use App\Support\GlobalNotificationFilter;
use App\Support\NotificationInboxWebFilters;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait WebNotificationInbox
{
    /**
     * When true (admin notifications index), list aggregates all users' notifications so audience_role filters work.
     */
    protected function inboxSpansAllUsers(): bool
    {
        return false;
    }

    protected function baseNotificationQuery(?string $audienceRole): mixed
    {
        if ($this->inboxSpansAllUsers()) {
            return GlobalNotificationFilter::allUsers($audienceRole);
        }

        return UserNotificationInbox::forUser(Auth::user(), $audienceRole);
    }

    protected function baseUnreadQuery(?string $audienceRole): mixed
    {
        if ($this->inboxSpansAllUsers()) {
            return GlobalNotificationFilter::unreadAllUsers($audienceRole);
        }

        return UserNotificationInbox::unreadForUser(Auth::user(), $audienceRole);
    }

    /** Audience + kind + search (not read/unread tab). */
    protected function scopedInboxQuery(Request $request): mixed
    {
        $query = $this->baseNotificationQuery($request->query('audience_role'));
        $query = NotificationInboxWebFilters::applyKind($query, $request->query('kind'));

        if ($request->filled('q')) {
            $query->where('data', 'like', '%' . $request->get('q') . '%');
        }

        return $query;
    }

    /** Same rows as the paginated list (includes read/unread tab). */
    protected function buildFilteredInboxQuery(Request $request): mixed
    {
        $query = $this->scopedInboxQuery($request);

        if ($request->get('filter') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->get('filter') === 'read') {
            $query->whereNotNull('read_at');
        }

        return $query;
    }

    protected function inboxUnreadCount(Request $request): int
    {
        return $this->scopedInboxQuery($request)->whereNull('read_at')->count();
    }

    protected function inboxFilteredTotal(Request $request): int
    {
        return $this->scopedInboxQuery($request)->count();
    }

    /**
     * @return list<int>
     */
    public static function allowedNotificationPerPage(): array
    {
        return [10, 15, 20, 30, 50];
    }

    protected function resolvedNotificationPerPage(Request $request): int
    {
        $requested = (int) $request->query('per_page', 15);
        $allowed = self::allowedNotificationPerPage();

        return in_array($requested, $allowed, true) ? $requested : 15;
    }

    /**
     * @return array{0: \Illuminate\Contracts\Pagination\LengthAwarePaginator, 1: int}
     */
    protected function paginatedInbox(Request $request): array
    {
        $query = $this->buildFilteredInboxQuery($request);
        $perPage = $this->resolvedNotificationPerPage($request);
        $notifications = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();
        $unreadCount = $this->inboxUnreadCount($request);

        return [$notifications, $unreadCount];
    }
}
