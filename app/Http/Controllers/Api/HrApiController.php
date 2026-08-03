<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\HrVisitAssignmentService;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Jobs\OptimizePublicDiskImageJob;

class HrApiController extends Controller
{
    /**
     * GET /api/hr/dashboard/summary
     * HR dashboard: profile (name, id, role), total_staff, new_hires, leave_requests count, pending_leave_requests list.
     */
    public function dashboardSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        $totalStaff = Employee::count();
        // New hires = only employees added (created) in the last 30 days, not all staff
        $newHiresCutoff = Carbon::now()->subDays(30);
        $newHires = Employee::where('created_at', '>=', $newHiresCutoff)->count();
        $leaveRequestsCount = LeaveRequest::where('status', 'pending')->count();

        $pendingLeaves = LeaveRequest::with('user.employee')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (LeaveRequest $lr) => $this->mapLeaveRequestForHr($lr))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'id' => $user->employee?->employee_id ?? ('HR-' . $user->id),
                'role' => 'HR Manager',
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
                'total_staff' => $totalStaff,
                'new_hires' => $newHires,
                'leave_requests' => $leaveRequestsCount,
                'pending_leave_requests' => $pendingLeaves,
            ],
        ]);
    }

    /**
     * GET /api/hr/dashboard/visit-assignments
     * Today and tomorrow: total visits, assigned, unassigned.
     * - total: visits scheduled for that day (scheduled_date = today/tomorrow).
     * - assigned: visits that have a technician assigned (technician_id is set).
     * - unassigned: visits with no technician yet (total - assigned).
     * Data is 0 when there are no visits for that day in the visits table.
     * Optional query: ?timezone=Asia/Dubai to use that timezone for today/tomorrow.
     */
    public function visitAssignments(Request $request): JsonResponse
    {
        $tz = $request->input('timezone');
        try {
            if ($tz) {
                $today = Carbon::today($tz);
                $tomorrow = Carbon::today($tz)->addDay();
            } else {
                $today = Carbon::today();
                $tomorrow = Carbon::today()->addDay();
            }
        } catch (\Exception $e) {
            $today = Carbon::today();
            $tomorrow = Carbon::today()->addDay();
        }

        $todayQuery = Visit::whereDate('scheduled_date', $today);
        $tomorrowQuery = Visit::whereDate('scheduled_date', $tomorrow);

        $todayTotal = (clone $todayQuery)->count();
        $todayAssigned = (clone $todayQuery)->whereNotNull('technician_id')->count();
        $todayUnassigned = $todayTotal - $todayAssigned;

        $tomorrowTotal = (clone $tomorrowQuery)->count();
        $tomorrowAssigned = (clone $tomorrowQuery)->whereNotNull('technician_id')->count();
        $tomorrowUnassigned = $tomorrowTotal - $tomorrowAssigned;

        return response()->json([
            'success' => true,
            'data' => [
                'today' => [
                    'total' => $todayTotal,
                    'assigned' => $todayAssigned,
                    'unassigned' => $todayUnassigned,
                ],
                'tomorrow' => [
                    'total' => $tomorrowTotal,
                    'assigned' => $tomorrowAssigned,
                    'unassigned' => $tomorrowUnassigned,
                ],
            ],
        ]);
    }

    /**
     * GET /api/hr/positions
     * List of positions for Add Employee form (Field Worker, Team Leader, etc.).
     */
    public function positions(Request $request): JsonResponse
    {
        $list = [
            ['value' => 'Field Worker', 'label' => 'Field Worker'],
            ['value' => 'Team Leader', 'label' => 'Team Leader'],
            ['value' => 'Area Manager', 'label' => 'Area Manager'],
            ['value' => 'HR Staff', 'label' => 'HR Staff'],
        ];

        return response()->json([
            'success' => true,
            'data' => $list,
        ]);
    }

    /**
     * POST /api/hr/leave-requests
     * Create a leave request (HR creates on behalf of employee). Body: user_id, leave_type, start_date, end_date, reason.
     */
    public function leaveRequestStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'leave_type' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:2000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $lr = LeaveRequest::create([
            'user_id' => $request->input('user_id'),
            'leave_type' => $request->input('leave_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'reason' => $request->input('reason'),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request created.',
            'data' => [
                'id' => $lr->id,
                'status' => $lr->status,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
            ],
        ], 201);
    }

    /**
     * GET /api/hr/leave-requests
     * List leave requests. Query: status=pending|approved|rejected (optional; all if omitted).
     */
    public function leaveRequestsIndex(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $query = LeaveRequest::with('user.employee')->orderByDesc('created_at');

        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $pendingCount = LeaveRequest::where('status', 'pending')->count();
        $approvedCount = LeaveRequest::where('status', 'approved')->count();
        $rejectedCount = LeaveRequest::where('status', 'rejected')->count();

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $paginator = $query->paginate($perPage);

        $list = $paginator->getCollection()->map(fn (LeaveRequest $lr) => $this->mapLeaveRequestForHr($lr))->values()->all();

        return response()->json([
            'success' => true,
            'data' => $list,
            'counts' => [
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /api/hr/leave-requests/{id}
     * Full detail for HR when they click on a leave: name, emp id, role, profile/avatar, leave type, dates, days, reason, status.
     */
    public function leaveRequestShow(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::with('user.employee')->find($id);
        if (! $lr) {
            return response()->json(['success' => false, 'message' => 'Leave request not found.'], 404);
        }

        $data = $this->mapLeaveRequestForHr($lr);
        $data['reviewed_at'] = $lr->reviewed_at?->toIso8601String();
        $data['created_at'] = $lr->created_at?->toIso8601String();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Map LeaveRequest + user to array for HR (list and detail): name, applicant_id, role, profile_picture_url/avatar, leave type, dates, duration_days, reason, status.
     */
    private function mapLeaveRequestForHr(LeaveRequest $lr): array
    {
        $u = $lr->user;
        $emp = $u?->employee;
        $applicantId = $emp?->employee_id ?? ('EMP-' . ($u?->id ?? 0));
        $days = $lr->start_date->diffInDays($lr->end_date) + 1;
        $name = $u?->name ?? 'N/A';
        $initial = $name !== 'N/A' ? mb_substr(trim($name), 0, 1) : '?';
        $role = $u?->role ?? null;
        $roleDisplay = $role ? ucfirst(str_replace('_', ' ', $role)) : 'N/A';

        return [
            'id' => $lr->id,
            'applicant_name' => $name,
            'applicant_id' => $applicantId,
            'applicant_role' => $roleDisplay,
            'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($u?->profile_picture ?? null, $initial),
            'leave_type' => $lr->leave_type,
            'duration_days' => $days,
            'start_date' => $lr->start_date->format('Y-m-d'),
            'end_date' => $lr->end_date->format('Y-m-d'),
            'reason' => $lr->reason,
            'status' => $lr->status,
        ];
    }

    /**
     * POST /api/hr/leave-requests/{id}/approve
     */
    public function leaveRequestApprove(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::find($id);
        if (! $lr) {
            return response()->json(['success' => false, 'message' => 'Leave request not found.'], 404);
        }
        if ($lr->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Leave request is not pending.'], 422);
        }

        $lr->status = 'approved';
        $lr->reviewed_by = $request->user()->id;
        $lr->reviewed_at = now();
        $lr->save();

        // Do NOT set user to inactive – account stays active; "on leave" is shown from approved LeaveRequest only.

        $lr->user?->notify(new \App\Notifications\LeaveRequestStatusNotification($lr->fresh(), 'approved'));

        return response()->json([
            'success' => true,
            'message' => 'Leave request approved.',
            'data' => [
                'id' => $lr->id,
                'status' => $lr->status,
            ],
        ]);
    }

    /**
     * POST /api/hr/leave-requests/{id}/reject
     */
    public function leaveRequestReject(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::find($id);
        if (! $lr) {
            return response()->json(['success' => false, 'message' => 'Leave request not found.'], 404);
        }
        if ($lr->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Leave request is not pending.'], 422);
        }

        $lr->status = 'rejected';
        $lr->reviewed_by = $request->user()->id;
        $lr->reviewed_at = now();
        $lr->save();

        $lr->user?->notify(new \App\Notifications\LeaveRequestStatusNotification($lr->fresh(), 'rejected'));

        return response()->json([
            'success' => true,
            'message' => 'Leave request rejected.',
            'data' => [
                'id' => $lr->id,
                'status' => $lr->status,
            ],
        ]);
    }

    /**
     * GET /api/hr/profile
     * HR Manager profile for Profile screen.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        $joiningDate = $user->employee?->joining_date ?? $user->created_at?->toDateString();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->employee?->phone,
                'id' => $user->employee?->employee_id ?? ('HR-' . $user->id),
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
                'rating' => 0,
                'jobs_completed' => 0,
                'total_earnings' => 0,
                'member_since' => $joiningDate,
                'specializations' => $user->employee?->specializations ?? [],
                'service_area' => $user->employee?->region ?? null,
            ],
        ]);
    }

    /**
     * PUT or POST /api/hr/profile
     * Form-data: name, email, phone, profile_picture (file). All optional.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        $profileFile = $request->file('profile_picture');
        if (is_array($profileFile)) {
            $profileFile = $profileFile[0] ?? null;
        }

        $input = $request->all();
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
        ];
        if ($profileFile) {
            $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp';
        }
        $validator = Validator::make(array_merge($input, ['profile_picture' => $profileFile]), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if ($request->has('name')) {
            $user->name = $request->input('name');
        }
        if ($request->has('email')) {
            $user->email = $request->input('email');
        }
        if ($request->has('phone')) {
            $user->phone = $request->input('phone') ?: null;
            if ($user->employee) {
                $user->employee->phone = $user->phone;
                $user->employee->save();
            }
        }

        if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
            $stored = $profileFile->store('profiles', 'public');
            $user->profile_picture = $stored;
            OptimizePublicDiskImageJob::dispatch($stored, 'user')->afterResponse();
        } elseif ($request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $stored = ProfilePictureUploadService::storeFromMultipartPut($request);
            if ($stored) {
                $user->profile_picture = $stored;
                OptimizePublicDiskImageJob::dispatch($stored, 'user')->afterResponse();
            }
        }

        $user->save();
        $user->load('employee');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->employee?->employee_id ?? ('HR-' . $user->id),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->employee?->phone,
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
            ],
        ]);
    }

    /**
     * GET /api/hr/visit-assignments/assign-screen
     * Technicians + paginated assignable visits (same shape as supervisor assign-tasks, company-wide).
     */
    public function visitAssignmentsAssignScreen(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 50)));
        $pending = HrVisitAssignmentService::assignableQuery()
            ->with(['supervisor', 'subscription.client', 'area', 'technician'])
            ->orderByRaw('escalated_at IS NOT NULL DESC')
            ->orderBy('scheduled_date')
            ->paginate($perPage);

        $teamMembers = User::role('technician')
            ->active()
            ->with('employee')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->mapTechnicianForHrAssign($u))
            ->values()
            ->all();

        $availableTasks = $pending->getCollection()->map(fn (Visit $v) => HrVisitAssignmentService::mapVisit($v))->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'team_members' => $teamMembers,
                'available_tasks' => $availableTasks,
            ],
            'meta' => [
                'current_page' => $pending->currentPage(),
                'last_page' => $pending->lastPage(),
                'per_page' => $pending->perPage(),
                'total' => $pending->total(),
            ],
        ]);
    }

    /**
     * GET /api/hr/visit-assignments
     * Paginated visits: scope=assignable (default) | all; optional date_from, date_on, status.
     */
    public function visitAssignmentsIndex(Request $request): JsonResponse
    {
        $scope = $request->get('scope', 'assignable');
        $query = $scope === 'all' ? Visit::query() : HrVisitAssignmentService::assignableQuery();
        $query->with(['supervisor', 'subscription.client', 'area', 'technician']);

        if ($request->filled('date_from')) {
            $query->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(1, min(100, (int) $request->get('per_page', 20)));
        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $list = $paginator->getCollection()->map(fn (Visit $v) => HrVisitAssignmentService::mapVisit($v))->values()->all();

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
     * GET /api/hr/visit-assignments/summary
     * Summary cards for Assign Visits screen: total, unassigned, pending_acceptance.
     * Supports same filters as list endpoint (scope, date_from, date_to, status).
     */
    public function visitAssignmentsSummary(Request $request): JsonResponse
    {
        $scope = $request->get('scope', 'assignable');
        $baseQuery = $scope === 'all' ? Visit::query() : HrVisitAssignmentService::assignableQuery();

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('scheduled_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $baseQuery->whereDate('scheduled_date', '<=', $request->input('date_to'));
        }
        if ($request->filled('status')) {
            $baseQuery->where('status', $request->input('status'));
        }

        $total = (clone $baseQuery)->count();
        $unassigned = (clone $baseQuery)->whereNull('technician_id')->count();
        $pendingAcceptance = (clone $baseQuery)->where('status', 'pending_acceptance')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_jobs' => $total,
                'unassigned' => $unassigned,
                'pending_acceptance' => $pendingAcceptance,
            ],
        ]);
    }

    /**
     * POST /api/hr/visit-assignments/{visitId}
     * Assign or re-offer visit to technician (company-wide; no zone restriction for HR).
     */
    public function visitAssignmentsAssign(Request $request, int $visitId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'technician_id' => 'required|integer|exists:users,id',
            'scheduled_date' => 'nullable|date',
            'note' => 'nullable|string|max:1000',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $result = HrVisitAssignmentService::assignVisit(
            $visitId,
            (int) $request->input('technician_id'),
            $request->input('scheduled_date'),
            $request->input('note')
        );

        if (! ($result['success'] ?? false)) {
            $status = ($result['message'] ?? '') === 'Visit not found or not assignable.' ? 404 : 422;

            return response()->json(array_merge(['success' => false], $result), $status);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
            'accept_by' => $result['accept_by'] ?? null,
        ], 200);
    }

    private function mapTechnicianForHrAssign(User $u): array
    {
        $today = Carbon::today()->toDateString();
        $onLeave = HrVisitAssignmentService::isTechnicianOnLeave($u->id, $today);
        $initial = mb_substr(trim((string) $u->name), 0, 1) ?: '?';

        return [
            'id' => $u->id,
            'name' => $u->name,
            'employee_id' => $u->employee?->employee_id ?? ('TECH-' . $u->id),
            'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($u->profile_picture ?? null, $initial),
            'on_leave_today' => $onLeave,
        ];
    }
}
