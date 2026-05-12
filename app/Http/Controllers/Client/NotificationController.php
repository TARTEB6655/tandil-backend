<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Concerns\WebNotificationInbox;
use App\Http\Controllers\Controller;
use App\Support\UserNotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Unified notifications: admin messages, tips (when published), help & support, leave, etc.
 * Single list with mark read and delete (single / selected / all).
 */
class NotificationController extends Controller
{
    use WebNotificationInbox;

    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index(Request $request): View
    {
        [$notifications, $unreadCount] = $this->paginatedInbox($request);
        $totalCount = $this->inboxFilteredTotal($request);

        return view('client.notifications.index', compact('notifications', 'unreadCount', 'totalCount'));
    }

    public function markAsRead($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->findOrFail($id);
        $notification->markAsRead();
        return back()->with('success', 'Marked as read.');
    }

    public function show(string $id): View|RedirectResponse
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
        if (! $notification) {
            return redirect()->route('client.notifications.index')->with('error', 'Notification not found.');
        }
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }
        return view('client.notifications.show', ['notification' => $notification]);
    }

    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();
        UserNotificationInbox::unreadForUser($user)->update(['read_at' => now()]);
        return back()->with('success', 'All marked as read.');
    }

    public function destroy($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = UserNotificationInbox::forUser($user)->find($id);
        if ($notification) {
            $notification->delete();
            return back()->with('success', 'Notification deleted.');
        }
        return back()->with('error', 'Notification not found.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);
        $user = Auth::user();
        $deleted = UserNotificationInbox::forUser($user)->whereIn('id', $request->ids)->delete();
        return back()->with('success', $deleted . ' notification(s) deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $user = Auth::user();
        $query = UserNotificationInbox::forUser($user);
        $count = $query->count();
        $query->delete();
        return back()->with('success', $count . ' notification(s) deleted.');
    }
}
