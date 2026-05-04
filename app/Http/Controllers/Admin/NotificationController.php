<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\WebNotificationInbox;
use App\Http\Controllers\Controller;
use App\Models\AdminNotificationBroadcast;
use App\Services\NotificationBroadcastService;
use App\Support\NotificationDeliveryAnalytics;
use App\Support\UserNotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use WebNotificationInbox;

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Display all notifications for the admin.
     */
    public function index(Request $request)
    {
        [$notifications, $unreadCount] = $this->paginatedInbox($request);
        $totalCount = $this->inboxFilteredTotal($request);

        return view('admin.notifications.index', compact('notifications', 'unreadCount', 'totalCount'));
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
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
        
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true, 'message' => 'Notification marked as read']);
        }
        
        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
    }

    public function show(string $id)
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
        if (! $notification) {
            return redirect()->route('admin.notifications.index')->with('error', 'Notification not found.');
        }
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
        return view('admin.notifications.show', ['notification' => $notification]);
    }

    /**
     * Mark notification as read and redirect to its target URL (e.g. support ticket or notifications list).
     * Used when user clicks a notification so it is marked read automatically and they are taken to the right page.
     */
    public function readAndRedirect($id)
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
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
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        UserNotificationInbox::unreadForUser($user)->update(['read_at' => now()]);
        
        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
        
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
        $user = Auth::user();
        $deleted = UserNotificationInbox::forUser($user)->whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', $deleted . ' notification(s) deleted.');
    }

    /**
     * Delete all notifications for the user.
     */
    public function destroyAll()
    {
        $user = Auth::user();
        $query = UserNotificationInbox::forUser($user);
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
        $request->validate([
            'since' => 'nullable|date_format:Y-m-d',
            'until' => 'nullable|date_format:Y-m-d',
        ]);
        $stats = NotificationDeliveryAnalytics::aggregate(
            $request->query('since'),
            $request->query('until')
        );

        return view('admin.notifications.delivery-stats', ['stats' => $stats]);
    }

    /**
     * Show form to send notifications.
     */
    public function create()
    {
        $roles = \Spatie\Permission\Models\Role::all();
        return view('admin.notifications.create', compact('roles'));
    }

    /**
     * Send notification to users/roles.
     */
    public function send(Request $request)
    {
        $validated = $request->validate(NotificationBroadcastService::validationRules());

        $broadcast = NotificationBroadcastService::send(Auth::user(), $validated);
        $counts = $broadcast->recipientCountsForApi();

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification sent to {$broadcast->total_recipients} user(s). By role — customers: {$counts['customers']}, technicians: {$counts['technicians']}, supervisors: {$counts['supervisors']}, area managers: {$counts['area_managers']}, HR: {$counts['hr']}, admins: {$counts['admins']}, other: {$counts['other']}.");
    }
}
