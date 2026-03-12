<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Leave requests submitted by technician or supervisor (for HR to approve/reject).
 * All actions use the authenticated user as the applicant.
 */
class EmployeeLeaveRequestController extends Controller
{
    /**
     * GET /api/technician/leave-requests or /api/supervisor/leave-requests
     * List my leave requests. Query: status=pending|approved|rejected (optional).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $query = LeaveRequest::where('user_id', $userId)->orderByDesc('created_at');

        $status = $request->get('status');
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $paginator = $query->paginate($perPage);

        $list = $paginator->getCollection()->map(function (LeaveRequest $lr) {
            $days = $lr->start_date->diffInDays($lr->end_date) + 1;
            return [
                'id' => $lr->id,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'duration_days' => $days,
                'reason' => $lr->reason,
                'status' => $lr->status,
                'reviewed_at' => $lr->reviewed_at?->toIso8601String(),
                'created_at' => $lr->created_at->toIso8601String(),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $list,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * POST /api/technician/leave-requests or /api/supervisor/leave-requests
     * Submit a leave request to HR. Body: leave_type, start_date, end_date, reason (optional).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lr = LeaveRequest::create([
            'user_id' => $request->user()->id,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted. HR will review it.',
            'data' => [
                'id' => $lr->id,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'duration_days' => $lr->start_date->diffInDays($lr->end_date) + 1,
                'reason' => $lr->reason,
                'status' => $lr->status,
                'created_at' => $lr->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/technician/leave-requests/{id} or /api/supervisor/leave-requests/{id}
     * Get one of my leave requests (own only).
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::where('user_id', $request->user()->id)->findOrFail($id);
        $days = $lr->start_date->diffInDays($lr->end_date) + 1;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $lr->id,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'duration_days' => $days,
                'reason' => $lr->reason,
                'status' => $lr->status,
                'reviewed_by' => $lr->reviewed_by,
                'reviewed_at' => $lr->reviewed_at?->toIso8601String(),
                'created_at' => $lr->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/technician/leave-request-types or /api/supervisor/leave-request-types
     * Leave types for dropdown when submitting a request (same list for technician/supervisor).
     */
    public function leaveTypes(): JsonResponse
    {
        $types = [
            ['value' => 'sick', 'label' => 'Sick leave'],
            ['value' => 'annual', 'label' => 'Annual Leave'],
            ['value' => 'unpaid', 'label' => 'Unpaid leave'],
            ['value' => 'paternity', 'label' => 'Paternity Leave'],
            ['value' => 'maternity', 'label' => 'Maternity Leave'],
            ['value' => 'other', 'label' => 'Other'],
        ];

        return response()->json(['success' => true, 'data' => $types]);
    }
}
