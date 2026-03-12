<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    // Only users with 'manage employees' permission can access these routes
    public function __construct()
    {
        // Use 'auth' for web routes, 'auth:sanctum' for API routes
        $this->middleware(['auth', 'role:hr|admin']);
    }

    // Show list of employees (technicians + supervisors; supervisors included even without Employee record)
    public function index(Request $request)
    {
        try {
            $query = Employee::with('user');

            // Search functionality (for web and for building API list)
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('employee_id', 'LIKE', "%{$search}%")
                        ->orWhere('designation', 'LIKE', "%{$search}%")
                        ->orWhere('region', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('email', 'LIKE', "%{$search}%");
                        });
                });
            }

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
                $today = Carbon::today();

                // 1) Rows from Employee table (technicians + any supervisors with employee record)
                $employeeRows = $query->orderBy('created_at', 'desc')->get();

                // 2) Staff (technicians + supervisors) who do NOT have an Employee record – include so HR sees all staff from DB.
                //    Use both users.role and Spatie role so seeder-created users (only User, no Employee) are included.
                $employeeUserIds = $employeeRows->pluck('user_id')->filter()->unique()->values()->all();
                $staffRoleQuery = function ($q, $roleName) {
                    $q->where('role', $roleName)
                        ->orWhereHas('roles', fn ($r) => $r->where('name', $roleName));
                };

                $techniciansWithoutEmployee = User::where(function ($q) use ($staffRoleQuery) {
                    $staffRoleQuery($q, 'technician');
                })->whereNotIn('id', $employeeUserIds);

                $supervisorsWithoutEmployee = User::where(function ($q) use ($staffRoleQuery) {
                    $staffRoleQuery($q, 'supervisor');
                })->whereNotIn('id', $employeeUserIds);

                if ($request->has('search') && $request->search) {
                    $search = $request->search;
                    $filter = function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%");
                    };
                    $techniciansWithoutEmployee->where($filter);
                    $supervisorsWithoutEmployee->where($filter);
                }
                $techniciansWithoutEmployee = $techniciansWithoutEmployee->orderBy('name')->get();
                $supervisorsWithoutEmployee = $supervisorsWithoutEmployee->orderBy('name')->get();

                // Build same-shaped items for technicians without Employee row.
                $technicianItems = $techniciansWithoutEmployee->map(function ($user) use ($today) {
                    $name = $user->name ?? '';
                    $initial = $name !== '' ? mb_substr(trim($name), 0, 1) : '?';
                    $profilePictureUrl = ProfilePictureUploadService::fullUrl($user->profile_picture) ?? '';
                    $leaveInfo = $this->employeeLeaveStatus($user->id, $today);
                    return [
                        'id' => 'tech-' . $user->id,
                        'user_id' => $user->id,
                        'name' => $name,
                        'email' => (string) ($user->email ?? ''),
                        'employee_id' => 'TECH-' . $user->id,
                        'phone' => (string) ($user->phone ?? ''),
                        'designation' => 'Technician',
                        'region' => '',
                        'joining_date' => null,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'profile_picture' => $user->profile_picture ?? '',
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => [
                            'id' => $user->id,
                            'name' => (string) ($user->name ?? ''),
                            'email' => (string) ($user->email ?? ''),
                            'phone' => (string) ($user->phone ?? ''),
                            'role' => (string) ($user->role ?? ''),
                            'profile_picture' => $user->profile_picture ?? '',
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ],
                    ];
                });

                // Build same-shaped items for each supervisor (no Employee row). Use non-null id/strings so app never gets null (avoids "toString of null" crash).
                $supervisorItems = $supervisorsWithoutEmployee->map(function ($user) use ($today) {
                    $name = $user->name ?? '';
                    $initial = $name !== '' ? mb_substr(trim($name), 0, 1) : '?';
                    $profilePictureUrl = ProfilePictureUploadService::fullUrl($user->profile_picture) ?? '';
                    $leaveInfo = $this->employeeLeaveStatus($user->id, $today);
                    return [
                        'id' => 'sup-' . $user->id,
                        'user_id' => $user->id,
                        'name' => $name,
                        'email' => (string) ($user->email ?? ''),
                        'employee_id' => 'SUP-' . $user->id,
                        'phone' => (string) ($user->phone ?? ''),
                        'designation' => 'Supervisor',
                        'region' => '',
                        'joining_date' => null,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'profile_picture' => $user->profile_picture ?? '',
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => [
                            'id' => $user->id,
                            'name' => (string) ($user->name ?? ''),
                            'email' => (string) ($user->email ?? ''),
                            'phone' => (string) ($user->phone ?? ''),
                            'role' => (string) ($user->role ?? ''),
                            'profile_picture' => $user->profile_picture ?? '',
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ],
                    ];
                });

                // 3) Map Employee rows to same response shape. Use empty string instead of null for strings so app never hits "toString of null".
                $employeeItems = $employeeRows->map(function ($employee) use ($today) {
                    $user = $employee->user;
                    $name = (string) ($employee->name ?? $user?->name ?? '');
                    $initial = $name !== '' ? mb_substr(trim($name), 0, 1) : '?';
                    $profilePictureUrl = $user ? (ProfilePictureUploadService::fullUrl($user->profile_picture) ?? '') : '';
                    $leaveInfo = $this->employeeLeaveStatus($employee->user_id, $today);
                    return [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $name,
                        'email' => (string) ($employee->email ?? $user?->email ?? ''),
                        'employee_id' => (string) ($employee->employee_id ?? ''),
                        'phone' => (string) ($employee->phone ?? $user?->phone ?? ''),
                        'designation' => (string) ($employee->designation ?? ''),
                        'region' => (string) ($employee->region ?? ''),
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'profile_picture' => $user?->profile_picture ?? '',
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => $user ? [
                            'id' => $user->id,
                            'name' => (string) ($user->name ?? ''),
                            'email' => (string) ($user->email ?? ''),
                            'phone' => (string) ($user->phone ?? ''),
                            'role' => (string) ($user->role ?? ''),
                            'profile_picture' => $user->profile_picture ?? '',
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ] : null,
                    ];
                });

                // Merge: employees first, then technicians without employee, then supervisors without employee; sort by name
                $all = $employeeItems->concat($technicianItems)->concat($supervisorItems)->values()->sortBy('name')->values()->all();
                $total = count($all);
                $page = max(1, (int) $request->get('page', 1));
                $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
                $paginator = new LengthAwarePaginator($slice, $total, $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);

                return response()->json([
                    'success' => true,
                    'message' => 'Employees retrieved successfully',
                    'data' => $paginator->items(),
                    'total' => $total,
                    'meta' => [
                        'current_page' => $paginator->currentPage(),
                        'last_page' => $paginator->lastPage(),
                        'per_page' => $paginator->perPage(),
                    ],
                ], 200);
            }

            // Web request - return view with pagination
            $employees = $query->orderBy('created_at', 'desc')->paginate(15);
            $search = $request->get('search', '');
            $leaveStatusMap = $this->leaveStatusMapForUserIds($employees->pluck('user_id')->filter()->unique()->values()->all());

            return view('hr.employees.index', compact('employees', 'search', 'leaveStatusMap'));
        } catch (\Exception $e) {
            // For API requests, return JSON error
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch employees: ' . $e->getMessage()
                ], 500);
            }

            // For web requests, redirect with error message
            return redirect()->route('hr.dashboard')
                ->with('error', 'Failed to load employees. Please try again.');
        }
    }

    // Show create form
    public function create()
    {
        return view('hr.employees.create');
    }

    // Add a new employee (accepts JSON or form-data; form-data me profile_picture file bhi bhej sakte hain)
    public function store(Request $request)
    {
        try {
            $profileFile = $request->file('profile_picture');
            if (is_array($profileFile)) {
                $profileFile = $profileFile[0] ?? null;
            }

            $rules = [
                'user_id' => 'nullable|exists:users,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'employee_id' => 'required|string|unique:employees,employee_id',
                'phone' => 'nullable|string|max:20',
                'designation' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
                'joining_date' => 'nullable|date',
            ];
            if ($profileFile) {
                $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
            }
            $data = $request->validate($rules);

            $employee = Employee::create(\Illuminate\Support\Arr::except($data, ['profile_picture']));

            // Agar linked user hai aur profile_picture file bheji hai to user ki photo update karo
            $user = $employee->user;
            if ($user) {
                if ($profileFile && is_object($profileFile) && method_exists($profileFile, 'store')) {
                    $stored = $profileFile->store('profiles', 'public');
                    $user->profile_picture = $stored;
                    ImageCompressionService::compressIfNeededFromPublicPath($stored);
                    $user->save();
                } elseif (str_contains((string) $request->header('Content-Type'), 'multipart/form-data')) {
                    $stored = ProfilePictureUploadService::storeFromMultipartPut($request);
                    if ($stored) {
                        $user->profile_picture = $stored;
                        ImageCompressionService::compressIfNeededFromPublicPath($stored);
                        $user->save();
                    }
                }
            }

            $employee->load('user');

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                $user = $employee->user;
                $name = $employee->name ?? $user?->name ?? null;
                $initial = $name ? mb_substr(trim($name), 0, 1) : '?';
                $profilePictureUrl = $user ? ProfilePictureUploadService::fullUrl($user->profile_picture) : null;
                $leaveInfo = $this->employeeLeaveStatus($employee->user_id, Carbon::today());
                return response()->json([
                    'success' => true,
                    'message' => 'Employee created successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $name,
                        'email' => $employee->email ?? $user?->email ?? null,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone ?? $user?->phone ?? null,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'profile_picture' => $user?->profile_picture ?? null,
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => $user ? [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'profile_picture' => $user->profile_picture ?? null,
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ] : null,
                    ]
                ], 201);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create employee. Please try again.')
                ->withInput();
        }
    }

    // Show one employee
    public function show(Request $request, $id)
    {
        try {
            $employee = Employee::with('user')->findOrFail($id);

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                $user = $employee->user;
                $name = $employee->name ?? $user?->name ?? null;
                $initial = $name ? mb_substr(trim($name), 0, 1) : '?';
                $profilePictureUrl = $user ? ProfilePictureUploadService::fullUrl($user->profile_picture) : null;
                $leaveInfo = $this->employeeLeaveStatus($employee->user_id, Carbon::today());
                return response()->json([
                    'success' => true,
                    'message' => 'Employee retrieved successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $name,
                        'email' => $employee->email ?? $user?->email ?? null,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone ?? $user?->phone ?? null,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'profile_picture' => $user?->profile_picture ?? null,
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => $user ? [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'profile_picture' => $user->profile_picture ?? null,
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ] : null,
                    ]
                ], 200);
            }

            // Web request - return view with leave status
            $leaveInfo = $this->employeeLeaveStatus($employee->user_id, Carbon::today());
            return view('hr.employees.show', compact('employee', 'leaveInfo'));
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('hr.employees.index')
                ->with('error', 'Employee not found.');
        }
    }

    // Show edit form
    public function edit($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        return view('hr.employees.edit', compact('employee'));
    }

    // Update an existing employee (accepts form-data for profile_picture when HR updates employee's photo)
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::with('user')->findOrFail($id);

            $profileFile = $request->file('profile_picture');
            if (is_array($profileFile)) {
                $profileFile = $profileFile[0] ?? null;
            }

            $rules = [
                'user_id' => 'nullable|exists:users,id',
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'employee_id' => 'sometimes|required|string|unique:employees,employee_id,' . $id,
                'phone' => 'nullable|string|max:20',
                'designation' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
                'joining_date' => 'nullable|date',
            ];
            if ($profileFile) {
                $rules['profile_picture'] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120';
            }
            $data = $request->validate($rules);

            $employee->update(\Illuminate\Support\Arr::except($data, ['profile_picture']));

            // If employee has linked user, sync name/email/phone and optionally update profile picture (form-data)
            $user = $employee->user;
            if ($user) {
                if ($request->has('name')) {
                    $user->name = $request->input('name');
                }
                if ($request->has('email')) {
                    $user->email = $request->input('email');
                }
                if ($request->has('phone')) {
                    $user->phone = $request->input('phone') ?: null;
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
            }

            $employee->load('user');

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                $user = $employee->user;
                $name = $employee->name ?? $user?->name ?? null;
                $initial = $name ? mb_substr(trim($name), 0, 1) : '?';
                $profilePictureUrl = $user ? ProfilePictureUploadService::fullUrl($user->profile_picture) : null;
                $leaveInfo = $this->employeeLeaveStatus($employee->user_id, Carbon::today());
                return response()->json([
                    'success' => true,
                    'message' => 'Employee updated successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $name,
                        'email' => $employee->email ?? $user?->email ?? null,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone ?? $user?->phone ?? null,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'profile_picture' => $user?->profile_picture ?? null,
                        'profile_picture_url' => $profilePictureUrl,
                        'initial' => mb_strtoupper($initial),
                        'status' => $leaveInfo['status'],
                        'leave_days' => $leaveInfo['leave_days'],
                        'leave_remaining_days' => $leaveInfo['leave_remaining_days'],
                        'leave_end_date' => $leaveInfo['leave_end_date'],
                        'user' => $user ? [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'role' => $user->role,
                            'profile_picture' => $user->profile_picture ?? null,
                            'profile_picture_url' => $profilePictureUrl,
                            'initial' => mb_strtoupper($initial),
                        ] : null,
                    ]
                ], 200);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update employee. Please try again.')
                ->withInput();
        }
    }

    // Delete an employee
    public function destroy(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee deleted successfully'
                ], 200);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee deleted successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('hr.employees.index')
                ->with('error', 'Failed to delete employee. Please try again.');
        }
    }

    /**
     * Get leave status for an employee (user_id): active or on_leave, with leave days when on leave.
     */
    private function employeeLeaveStatus(?int $userId, Carbon $today): array
    {
        $default = [
            'status' => 'active',
            'leave_days' => null,
            'leave_remaining_days' => null,
            'leave_end_date' => null,
        ];
        if (! $userId) {
            return $default;
        }

        $leave = LeaveRequest::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->first();

        if (! $leave) {
            return $default;
        }

        $totalDays = $leave->start_date->diffInDays($leave->end_date) + 1;
        $remainingDays = $today->diffInDays($leave->end_date, false) + 1;

        return [
            'status' => 'on_leave',
            'leave_days' => $totalDays,
            'leave_remaining_days' => max(0, $remainingDays),
            'leave_end_date' => $leave->end_date->format('Y-m-d'),
        ];
    }

    /**
     * Build a map of user_id => leave status for web views (avoid N+1).
     */
    private function leaveStatusMapForUserIds(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $today = Carbon::today();
        $leaves = LeaveRequest::whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('end_date')
            ->get();

        $map = [];
        foreach ($userIds as $uid) {
            $map[$uid] = ['status' => 'active', 'leave_days' => null, 'leave_remaining_days' => null, 'leave_end_date' => null];
        }
        foreach ($leaves as $leave) {
            $totalDays = $leave->start_date->diffInDays($leave->end_date) + 1;
            $remainingDays = $today->diffInDays($leave->end_date, false) + 1;
            $map[$leave->user_id] = [
                'status' => 'on_leave',
                'leave_days' => $totalDays,
                'leave_remaining_days' => max(0, $remainingDays),
                'leave_end_date' => $leave->end_date->format('Y-m-d'),
            ];
        }
        return $map;
    }
}
