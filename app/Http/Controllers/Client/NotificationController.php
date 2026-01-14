<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();

        return view('client.notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}

