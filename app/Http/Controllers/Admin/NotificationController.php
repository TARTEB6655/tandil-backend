<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WebNotificationInbox;
use App\Http\Controllers\Controller;
use App\Models\AdminNotificationBroadcast;
use App\Models\User;
use App\Services\NotificationBroadcastService;
use App\Support\GlobalNotificationFilter;
use App\Support\NotificationDeliveryAnalytics;
use App\Support\UserNotificationAudience;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use WebNotificationInbox;

    /** When true, inbox lists all users’ rows (statistics page); false = signed-in admin only. */
    protected bool $adminInboxAllUsers = false;

    protected function inboxSpansAllUsers(): bool
    {
        return $this->adminInboxAllUsers;
    }

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Admin’s personal notification inbox (notifiable = signed-in admin only).
     */
    public function index(Request $request)
    {
        $this->adminInboxAllUsers = false;
        [$notifications, $unreadCount] = $this->paginatedInbox($request);
        $totalCount = $this->inboxFilteredTotal($request);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
            'isSystemWideInbox' => false,
        ]);
    }

    /**
     * System-wide notification statistics / review (all users’ database notifications).
     */
    public function statistics(Request $request)
    {
        $this->adminInboxAllUsers = true;
        [$notifications, $unreadCount] = $this->paginatedInbox($request);
        $totalCount = $this->inboxFilteredTotal($request);

        return view('admin.notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $totalCount,
            'isSystemWideInbox' => true,
        ]);
    }

    /**
     * Broadcast delivery log (per-role recipient counts).
     */
    public function broadcastsIndex(Request $request): View
    {
        $items = AdminNotificationBroadcast::with('sentBy:id,name,email')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.notifications.broadcasts.index', compact('items'));
    }

    public function broadcastsShow(AdminNotificationBroadcast $broadcast): View
    {
        $broadcast->load('sentBy:id,name,email');

        return view('admin.notifications.broadcasts.show', compact('broadcast'));
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id)
    {
        $notification = GlobalNotificationFilter::findForAdminReview($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true, 'message' => 'Notification marked as read']);
        }

        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
    }

    public function show(string $id)
    {
        $notification = GlobalNotificationFilter::findForAdminReview($id);
        if (! $notification) {
            return redirect()->route('admin.notifications.index')->with('error', 'Notification not found.');
        }
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
        $backUrl = request()->query('from') === 'stats'
            ? route('admin.notifications.statistics')
            : route('admin.notifications.index');

        return view('admin.notifications.show', [
            'notification' => $notification,
            'backUrl' => $backUrl,
        ]);
    }

    /**
     * Mark notification as read and redirect to its target URL (e.g. support ticket or notifications list).
     * Used when user clicks a notification so it is marked read automatically and they are taken to the right page.
     */
    public function readAndRedirect($id)
    {
        $notification = GlobalNotificationFilter::findForAdminReview($id);
        if (! $notification) {
            return redirect()->route('admin.notifications.index')->with('error', 'Notification not found.');
        }
        $notification->markAsRead();
        $data = $notification->data;
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $url = route('admin.notifications.index');
        if (($meta['entity'] ?? null) === 'support_ticket' && ! empty($meta['ticket_id'] ?? null)) {
            $url = route('admin.support-tickets.show', $meta['ticket_id']);
        }
        return redirect($url);
    }

    /**
     * Mark all notifications as read.
     * Header dropdown marks only the admin's personal inbox; notifications index can opt into cross-role scope.
     */
    public function markAllAsRead(Request $request)
    {
        if ($request->boolean('admin_notifications_index')) {
            $this->scopedInboxQuery($request)->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            UserNotificationInbox::unreadForUser(Auth::user())->update(['read_at' => now()]);
        }

        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $notification = GlobalNotificationFilter::findForAdminReview($id);

        if ($notification) {
            $notification->delete();
            return redirect()->back()->with('success', 'Notification deleted');
        }

        return redirect()->back()->with('error', 'Notification not found');
    }

    /**
     * Delete selected notifications (POST ids[]).
     */
    public function destroyBulk(Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);
        $deleted = GlobalNotificationFilter::allUsers()->whereIn('id', $request->ids)->delete();

        return redirect()->back()->with('success', $deleted . ' notification(s) deleted.');
    }

    /**
     * Delete notifications matching current inbox filters (cross-role index) or all of the admin's own notifications.
     */
    public function destroyAll(Request $request)
    {
        if ($request->boolean('admin_notifications_index')) {
            $query = $this->buildFilteredInboxQuery($request);
            $count = $query->count();
            $query->delete();

            return redirect()->back()->with('success', $count . ' notification(s) deleted.');
        }

        $query = UserNotificationInbox::forUser(Auth::user());
        $count = $query->count();
        $query->delete();

        return redirect()->back()->with('success', $count . ' notification(s) deleted.');
    }

    /**
     * Get unread notifications count (for AJAX requests).
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        $count = UserNotificationInbox::unreadForUser($user)->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Aggregate delivery counts by notification type and audience (same data as GET /api/admin/notifications/delivery-stats).
     */
    public function deliveryStats(Request $request): View
    {
        $allowedAudience = array_merge(UserNotificationAudience::PRIORITY_ROLES, ['other', 'unknown']);

        $request->validate([
            'since' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d',
            'audience_role' => ['nullable', 'string', Rule::in($allowedAudience)],
        ]);

        $audienceRole = $request->query('audience_role');
        if ($audienceRole === '') {
            $audienceRole = null;
        }

        $stats = NotificationDeliveryAnalytics::aggregate(
            $request->query('since'),
            $request->query('until'),
            is_string($audienceRole) ? $audienceRole : null
        );

        return view('admin.notifications.delivery-stats', ['stats' => $stats]);
    }

    /**
     * Show form to send notifications.
     */
    public function create()
    {
        $roles = \Spatie\Permission\Models\Role::all();
        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get();

        return view('admin.notifications.create', compact('roles', 'users'));
    }

    /**
     * Send notification to users/roles.
     */
    public function send(Request $request)
    {
        $validated = $request->validate(NotificationBroadcastService::validationRules());

        $broadcast = NotificationBroadcastService::send(Auth::user(), $validated);
        $counts = $broadcast->recipientCountsForApi();

        return redirect()->route('admin.notifications.statistics')
            ->with('success', "Notification sent to {$broadcast->total_recipients} user(s). By role — customers: {$counts['customers']}, technicians: {$counts['technicians']}, supervisors: {$counts['supervisors']}, area managers: {$counts['area_managers']}, HR: {$counts['hr']}, admins: {$counts['admins']}, other: {$counts['other']}.");
    }
}
