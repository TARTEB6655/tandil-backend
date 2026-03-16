<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\AdminNotification;
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
        if ($status !== null && $status !== '') {
            $statusLower = strtolower((string) $status);
            if (in_array($statusLower, ['pending', 'approved', 'rejected'], true)) {
                $query->whereRaw('LOWER(status) = ?', [$statusLower]);
            }
        }

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $paginator = $query->paginate($perPage);

        $list = $paginator->getCollection()->map(function (LeaveRequest $lr) {
            $days = $lr->start_date->diffInDays($lr->end_date) + 1;
            $item = [
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
            if ($lr->working_days !== null) {
                $item['working_days'] = $lr->working_days;
            }
            return $item;
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
     * Submit a leave request to HR.
     * Body (form-data or JSON): leave_type, start_date, end_date, reason (optional).
     * Supervisor only: working_days (optional) – e.g. "mon,tue,wed,thu,fri,sat" or JSON array.
     */
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'leave_type' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ];
        $isSupervisor = $request->user()->hasRole('supervisor');
        if ($isSupervisor) {
            $rules['working_days'] = 'nullable|string|max:500'; // e.g. "mon,tue,wed,thu,fri,sat"
        }
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $payload = [
            'user_id' => $request->user()->id,
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ];
        if ($isSupervisor && $request->has('working_days')) {
            $wd = $request->input('working_days');
            $payload['working_days'] = is_array($wd)
                ? array_map('strtolower', array_map('trim', $wd))
                : array_map('strtolower', array_map('trim', explode(',', (string) $wd)));
            $allowed = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
            $payload['working_days'] = array_values(array_unique(array_filter($payload['working_days'], fn ($d) => in_array($d, $allowed, true))));
            if (empty($payload['working_days'])) {
                $payload['working_days'] = null;
            }
        }

        $lr = LeaveRequest::create($payload);

        // Notify all HR users that a new leave request has been submitted
        $applicant = $request->user();
        $title = 'New leave request submitted';
        $message = sprintf(
            '%s (%s) submitted a %s leave from %s to %s.',
            $applicant->name ?? 'Employee',
            $applicant->email ?? 'N/A',
            $lr->leave_type,
            $lr->start_date->format('Y-m-d'),
            $lr->end_date->format('Y-m-d')
        );
        $meta = [
            'type' => 'hr_leave_request',
            'leave_request_id' => $lr->id,
            'applicant_id' => $applicant->id,
            'applicant_role' => $applicant->role ?? null,
        ];
        // Include HR users matched either by users.role column or Spatie role, so no HR user is missed.
        $hrUsers = User::whereRaw('LOWER(role) = ?', ['hr'])
            ->orWhereHas('roles', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['hr']))
            ->get();
        foreach ($hrUsers as $hr) {
            $hr->notify(new AdminNotification($title, $message, $meta));
        }

        $data = [
            'id' => $lr->id,
            'leave_type' => $lr->leave_type,
            'start_date' => $lr->start_date->format('Y-m-d'),
            'end_date' => $lr->end_date->format('Y-m-d'),
            'duration_days' => $lr->start_date->diffInDays($lr->end_date) + 1,
            'reason' => $lr->reason,
            'status' => $lr->status,
            'created_at' => $lr->created_at->toIso8601String(),
        ];
        if ($isSupervisor) {
            $data['working_days'] = $lr->working_days;
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted. HR will review it.',
            'data' => $data,
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

        $data = [
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
        ];
        if ($lr->working_days !== null) {
            $data['working_days'] = $lr->working_days;
        }
        return response()->json(['success' => true, 'data' => $data]);
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
