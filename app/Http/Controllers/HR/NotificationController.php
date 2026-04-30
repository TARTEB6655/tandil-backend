<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Support\HrNotificationFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(): View
    {
        $user = Auth::user();
        $notifications = HrNotificationFilter::forUser($user)->orderBy('created_at', 'desc')->paginate(20);
        $unreadCount = HrNotificationFilter::unreadForUser($user)->count();

        return view('hr.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = HrNotificationFilter::forUser($user)->findOrFail($id);
        $notification->markAsRead();
        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();
        HrNotificationFilter::unreadForUser($user)->update(['read_at' => now()]);
        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = HrNotificationFilter::forUser($user)->find($id);
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
        $deleted = HrNotificationFilter::forUser($user)->whereIn('id', $request->ids)->delete();
        return back()->with('success', $deleted . ' notification(s) deleted.');
    }

    public function destroyAll(): RedirectResponse
    {
        $user = Auth::user();
        $query = HrNotificationFilter::forUser($user);
        $count = $query->count();
        $query->delete();
        return back()->with('success', $count . ' notification(s) deleted.');
    }
}

