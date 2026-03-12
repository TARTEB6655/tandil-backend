<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Supervisor web: apply leave and view my leave requests (same flow as technician – HR approves).
 */
class LeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    /** Leave types for dropdown (aligned with API). */
    private function leaveTypes(): array
    {
        return [
            ['value' => 'sick', 'label' => 'Sick leave'],
            ['value' => 'annual', 'label' => 'Annual Leave'],
            ['value' => 'unpaid', 'label' => 'Unpaid leave'],
            ['value' => 'paternity', 'label' => 'Paternity Leave'],
            ['value' => 'maternity', 'label' => 'Maternity Leave'],
            ['value' => 'other', 'label' => 'Other'],
        ];
    }

    /**
     * List my leave requests with optional status filter.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->get('status', '');
        $query = LeaveRequest::where('user_id', $user->id)->orderByDesc('created_at');

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $leaveRequests = $query->paginate(15)->withQueryString();
        $pendingCount = LeaveRequest::where('user_id', $user->id)->where('status', 'pending')->count();
        $approvedCount = LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->count();
        $rejectedCount = LeaveRequest::where('user_id', $user->id)->where('status', 'rejected')->count();

        $leaveTypes = $this->leaveTypes();
        return view('supervisor.leave-requests.index', compact(
            'leaveRequests',
            'status',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'leaveTypes'
        ));
    }

    /**
     * Show form to apply for leave.
     */
    public function create(): View
    {
        return view('supervisor.leave-requests.create', [
            'leaveTypes' => $this->leaveTypes(),
        ]);
    }

    /**
     * Store a new leave request (submitted to HR for approval).
     */
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return redirect()->route('supervisor.leave-requests.create')
                ->withErrors($validator)
                ->withInput();
        }

        LeaveRequest::create([
            'user_id' => $request->user()->id,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ]);

        return redirect()->route('supervisor.leave-requests.index')
            ->with('success', 'Leave request submitted. HR will review it.');
    }
}
