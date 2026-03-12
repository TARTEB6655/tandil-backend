<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestStatusNotification;
use App\Services\ProfilePictureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr|admin']);
    }

    /**
     * List leave requests with optional status filter (pending, approved, rejected).
     */
    public function index(Request $request): View
    {
        $status = $request->get('status', 'pending');
        $query = LeaveRequest::with('user.employee')->orderByDesc('created_at');

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $pendingCount = LeaveRequest::where('status', 'pending')->count();
        $approvedCount = LeaveRequest::where('status', 'approved')->count();
        $rejectedCount = LeaveRequest::where('status', 'rejected')->count();

        $leaveRequests = $query->paginate(15)->withQueryString();

        return view('hr.leave-requests.index', compact(
            'leaveRequests',
            'status',
            'pendingCount',
            'approvedCount',
            'rejectedCount'
        ));
    }

    /**
     * Show single leave request (full detail). For clickable cards from dashboard / manage list.
     */
    public function show(Request $request, int $id): View
    {
        $lr = LeaveRequest::with('user.employee')->findOrFail($id);
        $u = $lr->user;
        $applicantId = $u?->employee?->employee_id ?? ('EMP-' . ($u?->id ?? 0));
        $days = $lr->start_date->diffInDays($lr->end_date) + 1;
        $initial = $u?->name ? mb_substr(trim($u->name), 0, 1) : '?';
        $profilePictureUrl = ProfilePictureUploadService::fullUrlOrDefault($u?->profile_picture ?? null, $initial);
        $applicantRole = $u?->role ? ucfirst(str_replace('_', ' ', $u->role)) : 'N/A';

        return view('hr.leave-requests.show', compact(
            'lr',
            'applicantId',
            'days',
            'profilePictureUrl',
            'applicantRole'
        ));
    }

    /**
     * Approve a leave request.
     */
    public function approve(Request $request, int $id): RedirectResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Leave request is not pending.');
        }

        $leaveRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Do NOT set user to inactive – account stays active; "on leave" is shown from approved LeaveRequest only.

        return back()->with('success', 'Leave request approved.');
    }

    /**
     * Reject a leave request.
     */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);
        if ($leaveRequest->status !== 'pending') {
            return back()->with('error', 'Leave request is not pending.');
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $leaveRequest->user?->notify(new LeaveRequestStatusNotification($leaveRequest->fresh(), 'rejected'));

        return back()->with('success', 'Leave request rejected.');
    }
}
