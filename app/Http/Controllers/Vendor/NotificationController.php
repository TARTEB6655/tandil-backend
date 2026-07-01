<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Concerns\WebNotificationInbox;
use App\Http\Controllers\Controller;
use App\Support\UserNotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use WebNotificationInbox;

    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor', 'vendor.account']);
    }

    public function index(Request $request): View
    {
        [$notifications, $unreadCount] = $this->paginatedInbox($request);
        $totalCount = $this->inboxFilteredTotal($request);

        return view('vendor.notifications.index', compact('notifications', 'unreadCount', 'totalCount'));
    }

    public function markAsRead(string $id): RedirectResponse
    {
        $notification = UserNotificationInbox::forUser(Auth::user())->findOrFail($id);
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function show(string $id): View|RedirectResponse
    {
        $notification = UserNotificationInbox::forUser(Auth::user())->find($id);
        if ($notification === null) {
            return redirect()->route('vendor.notifications.index')->with('error', 'Notification not found.');
        }
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return view('vendor.notifications.show', ['notification' => $notification]);
    }

    public function markAllAsRead(): RedirectResponse
    {
        UserNotificationInbox::unreadForUser(Auth::user())->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $notification = UserNotificationInbox::forUser(Auth::user())->find($id);
        if ($notification) {
            $notification->delete();

            return back()->with('success', 'Notification deleted.');
        }

        return back()->with('error', 'Notification not found.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'uuid']);
        $deleted = UserNotificationInbox::forUser(Auth::user())->whereIn('id', $request->ids)->delete();

        return back()->with('success', $deleted.' notification(s) deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $query = UserNotificationInbox::forUser(Auth::user());
        $count = $query->count();
        $query->delete();

        return back()->with('success', $count.' notification(s) deleted.');
    }
}
