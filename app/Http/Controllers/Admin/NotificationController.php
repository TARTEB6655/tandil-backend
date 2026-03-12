<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Display all notifications for the admin.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get all notifications
        $query = $user->notifications();
        
        // Filter by read/unread
        if ($request->has('filter')) {
            if ($request->filter === 'unread') {
                $query->whereNull('read_at');
            } elseif ($request->filter === 'read') {
                $query->whereNotNull('read_at');
            }
        }
        
        $notifications = $query->orderBy('created_at', 'desc')->paginate(20);
        
        // Get counts
        $unreadCount = $user->unreadNotifications()->count();
        $totalCount = $user->notifications()->count();
        
        return view('admin.notifications.index', compact('notifications', 'unreadCount', 'totalCount'));
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
        
        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true, 'message' => 'Notification marked as read']);
        }
        
        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
    }

    /**
     * Mark notification as read and redirect to its target URL (e.g. support ticket or notifications list).
     * Used when user clicks a notification so it is marked read automatically and they are taken to the right page.
     */
    public function readAndRedirect($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
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
        $user->unreadNotifications->markAsRead();
        
        return redirect()->back()->with('success', 'All notifications marked as read');
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
        
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
        $deleted = $user->notifications()->whereIn('id', $request->ids)->delete();
        return redirect()->back()->with('success', $deleted . ' notification(s) deleted.');
    }

    /**
     * Delete all notifications for the user.
     */
    public function destroyAll()
    {
        $user = Auth::user();
        $count = $user->notifications()->count();
        $user->notifications()->delete();
        return redirect()->back()->with('success', $count . ' notification(s) deleted.');
    }

    /**
     * Get unread notifications count (for AJAX requests).
     */
    public function getUnreadCount()
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->count();
        
        return response()->json(['count' => $count]);
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
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'type' => 'required|in:all,role,users',
            'role' => 'required_if:type,role|exists:roles,name',
            'user_ids' => 'required_if:type,users|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = collect();

        if ($request->type === 'all') {
            $users = \App\Models\User::all();
        } elseif ($request->type === 'role') {
            $users = \App\Models\User::role($request->role)->get();
        } elseif ($request->type === 'users') {
            $users = \App\Models\User::whereIn('id', $request->user_ids)->get();
        }

        // Send notification to all selected users
        foreach ($users as $user) {
            $user->notify(new \App\Notifications\AdminNotification($request->title, $request->message));
        }

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification sent to {$users->count()} user(s) successfully.");
    }
}
