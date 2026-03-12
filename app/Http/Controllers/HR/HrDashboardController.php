<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(Request $request): View
    {
        try {
            $user = Auth::user();
            $user->load('employee');
            $search = $request->get('search', '');

            // Staff stats (aligned with API)
            $totalEmployees = Employee::count();
            $newHires = Employee::where('created_at', '>=', Carbon::now()->subDays(30))->count();
            $totalTechnicians = User::where('role', 'technician')->count();
            $totalSupervisors = User::where('role', 'supervisor')->count();
            $totalAreaManagers = User::where('role', 'area_manager')->count();

            // Leave requests (aligned with API)
            $leaveRequestsCount = LeaveRequest::where('status', 'pending')->count();
            $pendingLeaveRequests = LeaveRequest::with('user.employee')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(10)
                ->get();

            // Visit assignments today / tomorrow (aligned with API)
            $today = Carbon::today();
            $tomorrow = Carbon::today()->addDay();
            $todayQuery = Visit::whereDate('scheduled_date', $today);
            $tomorrowQuery = Visit::whereDate('scheduled_date', $tomorrow);
            $visitAssignments = [
                'today' => [
                    'total' => (clone $todayQuery)->count(),
                    'assigned' => (clone $todayQuery)->whereNotNull('technician_id')->count(),
                ],
                'tomorrow' => [
                    'total' => (clone $tomorrowQuery)->count(),
                    'assigned' => (clone $tomorrowQuery)->whereNotNull('technician_id')->count(),
                ],
            ];
            $visitAssignments['today']['unassigned'] = $visitAssignments['today']['total'] - $visitAssignments['today']['assigned'];
            $visitAssignments['tomorrow']['unassigned'] = $visitAssignments['tomorrow']['total'] - $visitAssignments['tomorrow']['assigned'];

            // Employees by designation
            try {
                $employeesByDesignation = Employee::select('designation', DB::raw('count(*) as count'))
                    ->whereNotNull('designation')
                    ->groupBy('designation')
                    ->get()
                    ->pluck('count', 'designation')
                    ->toArray();
            } catch (\Exception $e) {
                $employeesByDesignation = [];
            }

            // Recent employees
            try {
                $recentEmployees = Employee::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
            } catch (\Exception $e) {
                $recentEmployees = Employee::with('user')->limit(5)->get();
            }

            // Employees by region
            try {
                $employeesByRegion = Employee::select('region', DB::raw('count(*) as count'))
                    ->whereNotNull('region')
                    ->groupBy('region')
                    ->get()
                    ->pluck('count', 'region')
                    ->toArray();
            } catch (\Exception $e) {
                $employeesByRegion = [];
            }

            $hrId = $user->employee?->employee_id ?? ('HR-' . $user->id);

            $recentUserIds = $recentEmployees->pluck('user_id')->filter()->unique()->values()->all();
            $leaveStatusMap = $this->dashboardLeaveStatusMap($recentUserIds);

            // Staff on leave today (technicians + supervisors with approved leave) – for HR & admin visibility
            $staffOnLeaveToday = LeaveRequest::with('user')
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->whereHas('user', fn ($q) => $q->whereIn('role', ['technician', 'supervisor']))
                ->orderBy('end_date')
                ->get();

            return view('hr.dashboard', compact(
                'user',
                'hrId',
                'totalEmployees',
                'newHires',
                'leaveRequestsCount',
                'pendingLeaveRequests',
                'visitAssignments',
                'totalTechnicians',
                'totalSupervisors',
                'totalAreaManagers',
                'employeesByDesignation',
                'employeesByRegion',
                'recentEmployees',
                'leaveStatusMap',
                'staffOnLeaveToday',
                'search'
            ));
        } catch (\Exception $e) {
            \Log::error('HR Dashboard Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('hr.dashboard', [
                'user' => Auth::user(),
                'hrId' => 'HR-' . Auth::id(),
                'totalEmployees' => 0,
                'newHires' => 0,
                'leaveRequestsCount' => 0,
                'pendingLeaveRequests' => collect(),
                'visitAssignments' => ['today' => ['total' => 0, 'assigned' => 0, 'unassigned' => 0], 'tomorrow' => ['total' => 0, 'assigned' => 0, 'unassigned' => 0]],
                'totalTechnicians' => 0,
                'totalSupervisors' => 0,
                'totalAreaManagers' => 0,
                'employeesByDesignation' => [],
                'employeesByRegion' => [],
                'recentEmployees' => collect(),
                'leaveStatusMap' => [],
                'staffOnLeaveToday' => collect(),
                'search' => $request->get('search', ''),
            ]);
        }
    }

    private function dashboardLeaveStatusMap(array $userIds): array
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
            $map[$uid] = ['status' => 'active', 'leave_days' => null, 'leave_remaining_days' => null];
        }
        foreach ($leaves as $leave) {
            $totalDays = $leave->start_date->diffInDays($leave->end_date) + 1;
            $remainingDays = $today->diffInDays($leave->end_date, false) + 1;
            $map[$leave->user_id] = [
                'status' => 'on_leave',
                'leave_days' => $totalDays,
                'leave_remaining_days' => max(0, $remainingDays),
            ];
        }
        return $map;
    }
}
