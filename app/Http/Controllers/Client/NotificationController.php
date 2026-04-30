<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Support\GlobalNotificationFilter;
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
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $query = GlobalNotificationFilter::forUser($user);

        if ($request->get('filter') === 'unread') {
            $query->whereNull('read_at');
        } elseif ($request->get('filter') === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($request->filled('q')) {
            $query->where('data', 'like', '%' . $request->get('q') . '%');
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $unreadCount = GlobalNotificationFilter::unreadForUser($user)->count();

        return view('client.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = GlobalNotificationFilter::forUser($user)->findOrFail($id);
        $notification->markAsRead();
        return back()->with('success', 'Marked as read.');
    }

    public function show(string $id): View|RedirectResponse
    {
        $user = Auth::user();
        $notification = GlobalNotificationFilter::forUser($user)->find($id);
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
        GlobalNotificationFilter::unreadForUser($user)->update(['read_at' => now()]);
        return back()->with('success', 'All marked as read.');
    }

    public function destroy($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = GlobalNotificationFilter::forUser($user)->find($id);
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
        $deleted = GlobalNotificationFilter::forUser($user)->whereIn('id', $request->ids)->delete();
        return back()->with('success', $deleted . ' notification(s) deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $user = Auth::user();
        $query = GlobalNotificationFilter::forUser($user);
        $count = $query->count();
        $query->delete();
        return back()->with('success', $count . ' notification(s) deleted.');
    }
}
