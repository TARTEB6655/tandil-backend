<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
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

        return back()->with('success', 'Leave request rejected.');
    }
}
