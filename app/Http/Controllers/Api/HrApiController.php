<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        $newHires = Employee::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $leaveRequestsCount = LeaveRequest::where('status', 'pending')->count();

        $pendingLeaves = LeaveRequest::with('user.employee')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->map(function (LeaveRequest $lr) {
                $u = $lr->user;
                $emp = $u?->employee;
                $applicantId = $emp?->employee_id ?? ('EMP-' . $u->id);
                $days = $lr->start_date->diffInDays($lr->end_date) + 1;
                return [
                    'id' => $lr->id,
                    'applicant_name' => $u?->name ?? 'N/A',
                    'applicant_id' => $applicantId,
                    'leave_type' => $lr->leave_type,
                    'duration_days' => $days,
                    'start_date' => $lr->start_date->format('Y-m-d'),
                    'end_date' => $lr->end_date->format('Y-m-d'),
                    'reason' => $lr->reason,
                    'status' => $lr->status,
                ];
            })
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
     */
    public function visitAssignments(Request $request): JsonResponse
    {
        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();

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

        $list = $paginator->getCollection()->map(function (LeaveRequest $lr) {
            $u = $lr->user;
            $emp = $u?->employee;
            $applicantId = $emp?->employee_id ?? ('EMP-' . $u->id);
            $days = $lr->start_date->diffInDays($lr->end_date) + 1;
            return [
                'id' => $lr->id,
                'applicant_name' => $u?->name ?? 'N/A',
                'applicant_id' => $applicantId,
                'leave_type' => $lr->leave_type,
                'duration_days' => $days,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'reason' => $lr->reason,
                'status' => $lr->status,
            ];
        })->values()->all();

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
     */
    public function leaveRequestShow(Request $request, int $id): JsonResponse
    {
        $lr = LeaveRequest::with('user.employee')->find($id);
        if (! $lr) {
            return response()->json(['success' => false, 'message' => 'Leave request not found.'], 404);
        }

        $u = $lr->user;
        $emp = $u?->employee;
        $applicantId = $emp?->employee_id ?? ('EMP-' . $u->id);
        $days = $lr->start_date->diffInDays($lr->end_date) + 1;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $lr->id,
                'applicant_name' => $u?->name ?? 'N/A',
                'applicant_id' => $applicantId,
                'leave_type' => $lr->leave_type,
                'duration_days' => $days,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'reason' => $lr->reason,
                'status' => $lr->status,
                'reviewed_at' => $lr->reviewed_at?->toIso8601String(),
            ],
        ]);
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
            ImageCompressionService::compressIfNeededFromPublicPath($stored);
        } elseif ($request->isMethod('PUT') && str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
            $stored = ProfilePictureUploadService::storeFromMultipartPut($request);
            if ($stored) {
                $user->profile_picture = $stored;
                ImageCompressionService::compressIfNeededFromPublicPath($stored);
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
}
