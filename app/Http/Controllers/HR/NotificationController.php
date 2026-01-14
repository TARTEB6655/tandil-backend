<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(): View
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);

        return view('hr.notifications.index', compact('notifications'));
    }

    public function markAsRead($id): RedirectResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(): RedirectResponse
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}

