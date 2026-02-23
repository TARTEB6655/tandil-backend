<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Tip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * Show notifications (tips created by admin) – same data as API GET /api/user/notifications.
     */
    public function index(Request $request)
    {
        $tips = Tip::where('status', 'published')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('client.notifications.index', compact('tips'));
    }

    public function markAsRead($id)
    {
        $tip = Tip::where('status', 'published')->findOrFail($id);
        // No read state stored; acknowledge only
        return back()->with('success', 'Marked as read.');
    }

    public function markAllAsRead()
    {
        return back()->with('success', 'All marked as read.');
    }
}

