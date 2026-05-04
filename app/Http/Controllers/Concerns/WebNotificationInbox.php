<?php

namespace App\Http\Controllers\Concerns;

use App\Support\NotificationInboxWebFilters;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait WebNotificationInbox
{
    protected function baseNotificationQuery(?string $audienceRole): mixed
    {
        return UserNotificationInbox::forUser(Auth::user(), $audienceRole);
    }

    protected function baseUnreadQuery(?string $audienceRole): mixed
    {
        return UserNotificationInbox::unreadForUser(Auth::user(), $audienceRole);
    }

    protected function inboxUnreadCount(Request $request): int
    {
        $q = $this->baseUnreadQuery($request->query('audience_role'));

        return NotificationInboxWebFilters::applyKind($q, $request->query('kind'))->count();
    }

    protected function inboxFilteredTotal(Request $request): int
    {
        $q = $this->baseNotificationQuery($request->query('audience_role'));

        return NotificationInboxWebFilters::applyKind($q, $request->query('kind'))->count();
    }

    /**
     * @return array{0: \Illuminate\Contracts\Pagination\LengthAwarePaginator, 1: int}
     */
    protected function paginatedInbox(Request $request): array
    {
        $query = $this->baseNotificationQuery($request->query('audience_role'));
        $query = NotificationInboxWebFilters::applyKind($query, $request->query('kind'));

        if ($request->get('filter') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->get('filter') === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($request->filled('q')) {
            $query->where('data', 'like', '%' . $request->get('q') . '%');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $unreadCount = $this->inboxUnreadCount($request);

        return [$notifications, $unreadCount];
    }
}
