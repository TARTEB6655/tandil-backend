<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Models\Area;
use App\Models\Order;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\TechnicianVacation;
use App\Models\User;
use App\Models\Visit;
use App\Services\ImageCompressionService;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AreaManagerApiController extends Controller
{
    /**
     * GET /api/area-manager/dashboard/summary
     * Profile + KPIs: total_farms, active_subscriptions, monthly_revenue, team (count), active, done.
     */
    public function dashboardSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('employee');

        $totalFarms = Area::count();
        $activeSubscriptions = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', Carbon::today())
            ->count();
        $monthlyRevenue = Order::where('payment_status', 'paid')
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_amount');

        $areaIds = Area::pluck('id')->toArray();
        $teamLeadersCount = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
        $visitQuery = Visit::whereIn('area_id', $areaIds);
        $activeVisits = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
        $doneVisits = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();

        return response()->json([
            'success' => true,
            'data' => [
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
                'name' => $user->name,
                'id' => $user->employee?->employee_id ?? ('AM-' . $user->id),
                'role' => 'Area Manager',
                'region' => $user->employee?->region ?? null,
                'total_farms' => $totalFarms,
                'active_subscriptions' => $activeSubscriptions,
                'monthly_revenue' => round((float) $monthlyRevenue, 2),
                'team' => $teamLeadersCount,
                'active' => $activeVisits,
                'done' => $doneVisits,
            ],
        ]);
    }

    /**
     * GET /api/area-manager/dashboard/alerts
     * System-generated alerts: overdue visits, expiring subscriptions, stuck visits, workers on leave.
     * Also returns leave_by_supervisor so UI can show "Supervisor X has N on leave".
     */
    public function dashboardAlerts(Request $request): JsonResponse
    {
        $areaIds = Area::pluck('id')->toArray();
        $today = Carbon::today();
        $alerts = [];

        // 1) Overdue visits (scheduled in the past, not yet completed)
        $overdueCount = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
            ->where('scheduled_date', '<', $today)
            ->count();
        if ($overdueCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $overdueCount === 1
                    ? '1 visit is overdue.'
                    : "{$overdueCount} visits are overdue.",
                'timestamp' => Carbon::now()->toIso8601String(),
            ];
        }

        // 2) Subscriptions expiring in next 7 days
        $expiringCount = Subscription::where('payment_status', 'paid')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)])
            ->count();
        if ($expiringCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $expiringCount === 1
                    ? '1 subscription is expiring in the next 7 days.'
                    : "{$expiringCount} subscriptions are expiring in the next 7 days.",
                'timestamp' => Carbon::now()->toIso8601String(),
            ];
        }

        // 3) Visits stuck in_progress for more than 24 hours
        $stuckCount = Visit::whereIn('area_id', $areaIds)
            ->where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->where('started_at', '<', Carbon::now()->subHours(24))
            ->count();
        if ($stuckCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $stuckCount === 1
                    ? '1 visit has been in progress for over 24 hours.'
                    : "{$stuckCount} visits have been in progress for over 24 hours.",
                'timestamp' => Carbon::now()->toIso8601String(),
            ];
        }

        // 4) Workers on leave (technicians in region whose leave covers today)
        $technicianIdsInRegion = DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $onLeaveToday = TechnicianVacation::whereIn('user_id', $technicianIdsInRegion)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();
        if ($onLeaveToday > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => $onLeaveToday === 1
                    ? '1 worker is on leave today.'
                    : "{$onLeaveToday} workers are on leave today.",
                'timestamp' => Carbon::now()->toIso8601String(),
            ];
        }

        // Leave by supervisor (for UI: "Supervisor X has N on leave")
        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $leaveBySupervisor = [];
        foreach ($supervisorIds as $supId) {
            $u = User::find($supId);
            if (! $u) {
                continue;
            }
            $supAreaIds = $u->supervisedAreaIds();
            if (empty($supAreaIds)) {
                continue;
            }
            $techIds = DB::table('area_technician')->whereIn('area_id', $supAreaIds)->distinct()->pluck('user_id');
            $count = TechnicianVacation::whereIn('user_id', $techIds)
                ->where('start_date', '<=', $today)
                ->where('end_date', '>=', $today)
                ->count();
            if ($count > 0) {
                $leaveBySupervisor[] = [
                    'supervisor_id' => $u->id,
                    'supervisor_name' => $u->name,
                    'on_leave' => $count,
                ];
            }
        }

        // Fallback: when no warnings, return a single info alert so UI always has something to show
        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'info',
                'message' => 'No alerts at this time. All visits and subscriptions are on track.',
                'timestamp' => Carbon::now()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $alerts,
            'leave_by_supervisor' => $leaveBySupervisor,
        ]);
    }

    /**
     * GET /api/area-manager/region-map
     * Data for Region Map screen: areas (id, name, location, country, active, done) and team leaders (id, name, location).
     * React Native map can plot areas and supervisor pins from this single response.
     */
    public function regionMap(Request $request): JsonResponse
    {
        $areaIds = Area::pluck('id')->toArray();

        $areas = Area::orderBy('name')->get()->map(function (Area $a) {
            $visitQuery = Visit::where('area_id', $a->id);
            $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
            $done = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            return [
                'id' => $a->id,
                'name' => $a->name,
                'location' => $a->location,
                'country' => $a->country,
                'active' => $active,
                'done' => $done,
            ];
        })->values()->all();

        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $teamLeaders = User::role('supervisor')
            ->whereIn('id', $supervisorIds)
            ->with('employee')
            ->get()
            ->map(function (User $u) {
                $firstArea = $u->supervisedAreas()->first();
                $location = $firstArea?->name ?? $firstArea?->location ?? $u->employee?->region ?? null;
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                    'location' => $location,
                ];
            })->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'areas' => $areas,
                'team_leaders' => $teamLeaders,
            ],
        ]);
    }

    /**
     * GET /api/area-manager/team-leaders
     * List supervisors (team leaders) with performance %, team size, active, done.
     */
    public function teamLeaders(Request $request): JsonResponse
    {
        $supervisorIds = DB::table('area_supervisor')->distinct()->pluck('user_id');
        $supervisors = User::role('supervisor')
            ->whereIn('id', $supervisorIds)
            ->with('employee')
            ->get();

        $list = $supervisors->map(function (User $u) {
            $areaIds = $u->supervisedAreaIds();
            $firstArea = $u->supervisedAreas()->first();
            $location = $firstArea?->name ?? $firstArea?->location ?? $u->employee?->region ?? null;
            $teamCount = empty($areaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
            // Count all jobs in this supervisor's scope: assigned to them OR in their areas (includes unassigned and any technician).
            $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
                $q->where('supervisor_id', $u->id);
                if (! empty($areaIds)) {
                    $q->orWhereIn('area_id', $areaIds);
                }
            });
            $activeCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
            $doneCount = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            $total = $activeCount + $doneCount;
            $performance = $total > 0 ? round(($doneCount / $total) * 100, 0) : 0;
            $initial = mb_substr(trim($u->name), 0, 1) ?: '?';

            return [
                'id' => $u->id,
                'name' => $u->name,
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'initial' => mb_strtoupper($initial),
                'location' => $location,
                'profile_picture' => $u->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($u->profile_picture, $initial),
                'performance_percent' => $performance,
                'team' => $teamCount,
                'active' => $activeCount,
                'done' => $doneCount,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $list,
            'meta' => ['total' => count($list)],
        ]);
    }

    /**
     * GET /api/area-manager/team-leaders/{id}
     * Single team leader (supervisor) detail with stats.
     */
    public function teamLeaderShow(Request $request, int $id): JsonResponse
    {
        $u = User::role('supervisor')->where('id', $id)->with('employee')->first();
        if (! $u) {
            return response()->json(['success' => false, 'message' => 'Team leader not found.'], 404);
        }

        $areaIds = $u->supervisedAreaIds();
        $teamCount = empty($areaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
        // Count all jobs in this supervisor's scope: assigned to them OR in their areas (so 5 added + 1 completed all show).
        $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
            $q->where('supervisor_id', $u->id);
            if (! empty($areaIds)) {
                $q->orWhereIn('area_id', $areaIds);
            }
        });
        $activeCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
        $doneCount = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
        $total = $activeCount + $doneCount;
        $performance = $total > 0 ? round(($doneCount / $total) * 100, 0) : 0;
        $initial = mb_substr(trim($u->name), 0, 1) ?: '?';

        $firstArea = $u->supervisedAreas()->first();
        $location = $firstArea?->name ?? $firstArea?->location ?? $u->employee?->region ?? null;

        $data = [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
            'initial' => mb_strtoupper($initial),
            'location' => $location,
            'profile_picture' => $u->profile_picture,
            'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($u->profile_picture, $initial),
            'performance_percent' => $performance,
            'team' => $teamCount,
            'active' => $activeCount,
            'done' => $doneCount,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/area-manager/team-leaders/{id}/jobs
     * List jobs (visits) for this team leader. Default: processing only (pending, scheduled, in_progress).
     * Query: status=processing (default) | all | completed.
     */
    public function teamLeaderJobs(Request $request, int $id): JsonResponse
    {
        $u = User::role('supervisor')->where('id', $id)->with('employee')->first();
        if (! $u) {
            return response()->json(['success' => false, 'message' => 'Team leader not found.'], 404);
        }

        $areaIds = $u->supervisedAreaIds();
        $visitQuery = Visit::with(['subscription.client', 'technician:id,name', 'area:id,name'])
            ->where(function ($q) use ($u, $areaIds) {
                $q->where('supervisor_id', $u->id);
                if (! empty($areaIds)) {
                    $q->orWhereIn('area_id', $areaIds);
                }
            });

        $statusFilter = strtolower((string) $request->get('status', 'processing'));
        if ($statusFilter === 'processing') {
            $visitQuery->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started']);
        } elseif ($statusFilter === 'completed') {
            $visitQuery->whereIn('status', ['completed', 'approved']);
        }

        $baseCountQuery = Visit::where(function ($q) use ($u, $areaIds) {
            $q->where('supervisor_id', $u->id);
            if (! empty($areaIds)) {
                $q->orWhereIn('area_id', $areaIds);
            }
        });
        $totalActive = (clone $baseCountQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
        $totalDone = (clone $baseCountQuery)->whereIn('status', ['completed', 'approved'])->count();

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $visits = $visitQuery->orderByRaw("CASE WHEN status IN ('in_progress','started') THEN 0 WHEN status IN ('pending','scheduled') THEN 1 ELSE 2 END")
            ->orderBy('scheduled_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $list = $visits->getCollection()->map(function (Visit $v) {
            $clientName = $v->subscription?->client?->name ?? 'N/A';
            $areaName = $v->area?->name ?? 'N/A';
            $technicianName = $v->technician?->name ?? null;
            return [
                'id' => $v->id,
                'visit_id' => $v->id,
                'status' => $v->status,
                'status_display' => \Illuminate\Support\Str::title(str_replace('_', ' ', $v->status)),
                'scheduled_date' => $v->scheduled_date?->format('Y-m-d'),
                'scheduled_date_display' => $v->scheduled_date?->format('M d, Y'),
                'client_name' => $clientName,
                'area_name' => $areaName,
                'technician_name' => $technicianName,
                'technician_id' => $v->technician_id,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => [
                'team_leader' => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                    'active_count' => $totalActive,
                    'done_count' => $totalDone,
                    'total_jobs' => $totalActive + $totalDone,
                ],
                'jobs' => $list,
            ],
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'per_page' => $visits->perPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    /**
     * GET /api/area-manager/team-leaders/{id}/members
     * Team members (technicians) under this team leader. Area manager clicks on a team → see all members, who is active, linked to whom.
     * Returns: members with id, name, employee_id, area(s), team_leader (linked), active count, done count. Updates when assignments change.
     */
    public function teamLeaderMembers(Request $request, int $id): JsonResponse
    {
        $supervisor = User::role('supervisor')->where('id', $id)->with('employee')->first();
        if (! $supervisor) {
            return response()->json(['success' => false, 'message' => 'Team leader not found.'], 404);
        }

        $areaIds = $supervisor->supervisedAreaIds();
        if (empty($areaIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'team_leader' => [
                        'id' => $supervisor->id,
                        'name' => $supervisor->name,
                        'employee_id' => $supervisor->employee?->employee_id ?? ('SUP-' . $supervisor->id),
                        'location' => null,
                    ],
                    'members' => [],
                ],
                'meta' => ['total' => 0],
            ]);
        }

        $technicianIds = DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $members = User::role('technician')->whereIn('id', $technicianIds)->with('employee')->get()->map(function (User $tech) use ($areaIds, $supervisor) {
            $techAreaIds = DB::table('area_technician')->where('user_id', $tech->id)->whereIn('area_id', $areaIds)->pluck('area_id');
            $areas = Area::whereIn('id', $techAreaIds)->get();
            $areaNames = $areas->pluck('name')->filter()->values()->all();
            $location = $areas->map(fn ($a) => trim($a->name . ($a->location ? ', ' . $a->location : '')))->filter()->unique()->values()->implode(', ') ?: null;
            $visitQuery = Visit::where('technician_id', $tech->id)->whereIn('area_id', $areaIds);
            $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
            $done = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            $initial = mb_substr(trim($tech->name), 0, 1) ?: '?';
            return [
                'id' => $tech->id,
                'name' => $tech->name,
                'employee_id' => $tech->employee?->employee_id ?? ('TEC-' . $tech->id),
                'initial' => mb_strtoupper($initial),
                'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($tech->profile_picture, $initial),
                'location' => $location ?? implode(', ', $areaNames) ?: null,
                'area_names' => $areaNames,
                'area_ids' => $techAreaIds->values()->all(),
                'linked_to' => [
                    'supervisor_id' => $supervisor->id,
                    'supervisor_name' => $supervisor->name,
                    'supervisor_employee_id' => $supervisor->employee?->employee_id ?? ('SUP-' . $supervisor->id),
                ],
                'active' => $active,
                'done' => $done,
                'total_jobs' => $active + $done,
                'team_leader_id' => $supervisor->id,
            ];
        })->values()->all();

        $firstArea = $supervisor->supervisedAreas()->first();
        $location = $firstArea?->name ?? $firstArea?->location ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'team_leader' => [
                    'id' => $supervisor->id,
                    'name' => $supervisor->name,
                    'employee_id' => $supervisor->employee?->employee_id ?? ('SUP-' . $supervisor->id),
                    'location' => $location,
                ],
                'members' => $members,
            ],
            'meta' => ['total' => count($members)],
        ]);
    }

    /**
     * GET /api/area-manager/teams/members/{id}/jobs
     * Jobs (visits) for this team member (technician). Optional ?team_id= (supervisor id) scopes to that team's areas so counts match members list.
     * Query: status=processing (default) | all | completed, per_page, team_id (optional).
     */
    public function teamMemberJobs(Request $request, int $id): JsonResponse
    {
        $technician = User::role('technician')->where('id', $id)->with('employee')->first();
        if (! $technician) {
            return response()->json(['success' => false, 'message' => 'Team member not found.'], 404);
        }

        $teamId = $request->get('team_id');
        $areaIds = [];
        if ($teamId) {
            $supervisor = User::role('supervisor')->where('id', $teamId)->first();
            if ($supervisor) {
                $areaIds = $supervisor->supervisedAreaIds();
            }
        }
        if (empty($areaIds)) {
            $areaIds = Area::pluck('id')->toArray();
        }

        $visitQuery = Visit::with(['subscription.client', 'area:id,name,location', 'supervisor:id,name', 'technician:id,name', 'technician.employee'])
            ->where('technician_id', $id)
            ->whereIn('area_id', $areaIds);

        $baseVisitQuery = Visit::where('technician_id', $id)->whereIn('area_id', $areaIds);
        $totalJobsCount = (clone $baseVisitQuery)->count();
        $activeCount = (clone $baseVisitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
        $doneCount = (clone $baseVisitQuery)->whereIn('status', ['completed', 'approved'])->count();

        $statusFilter = strtolower((string) $request->get('status', 'processing'));
        if ($statusFilter === 'processing') {
            $visitQuery->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started']);
        } elseif ($statusFilter === 'completed') {
            $visitQuery->whereIn('status', ['completed', 'approved']);
        }

        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));
        $visits = $visitQuery->orderByRaw("CASE WHEN status IN ('in_progress','started') THEN 0 WHEN status IN ('pending','scheduled') THEN 1 ELSE 2 END")
            ->orderBy('scheduled_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $list = $visits->getCollection()->map(function (Visit $v) {
            $tech = $v->technician;
            return [
                'id' => $v->id,
                'visit_id' => $v->id,
                'status' => $v->status,
                'status_display' => \Illuminate\Support\Str::title(str_replace('_', ' ', $v->status)),
                'scheduled_date' => $v->scheduled_date?->format('Y-m-d'),
                'scheduled_date_display' => $v->scheduled_date?->format('M d, Y'),
                'client_name' => $v->subscription?->client?->name ?? 'N/A',
                'area_name' => $v->area?->name ?? 'N/A',
                'area_location' => $v->area?->location ?? null,
                'supervisor_name' => $v->supervisor?->name ?? null,
                'supervisor_id' => $v->supervisor_id,
                'is_processing' => in_array($v->status, ['in_progress', 'started']),
                'completed_at' => $v->completed_at?->toIso8601String(),
                'completed_at_display' => $v->completed_at?->format('M d, Y H:i'),
                'started_at' => $v->started_at?->toIso8601String(),
                'started_at_display' => $v->started_at?->format('M d, Y H:i'),
                'completed_by' => $tech ? [
                    'id' => $tech->id,
                    'name' => $tech->name,
                    'employee_id' => $tech->employee?->employee_id ?? ('TEC-' . $tech->id),
                ] : null,
                'technician_id' => $v->technician_id,
                'technician_name' => $tech?->name ?? null,
                'price' => $v->price !== null ? (float) $v->price : null,
                'notes' => $v->notes ? \Illuminate\Support\Str::limit($v->notes, 200) : null,
            ];
        })->values()->all();

        $initial = mb_substr(trim($technician->name), 0, 1) ?: '?';
        return response()->json([
            'success' => true,
            'data' => [
                'team_member' => [
                    'id' => $technician->id,
                    'name' => $technician->name,
                    'employee_id' => $technician->employee?->employee_id ?? ('TEC-' . $technician->id),
                    'initial' => mb_strtoupper($initial),
                    'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($technician->profile_picture, $initial),
                    'active_count' => $activeCount,
                    'done_count' => $doneCount,
                    'total_jobs' => $totalJobsCount,
                ],
                'jobs' => $list,
            ],
            'meta' => [
                'current_page' => $visits->currentPage(),
                'last_page' => $visits->lastPage(),
                'per_page' => $visits->perPage(),
                'total' => $visits->total(),
            ],
        ]);
    }

    /**
     * GET /api/area-manager/analytics?period=today|week|month
     * Visits, completion %, avg time, active teams, weekly_trend, top_teams.
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        // Area manager sees all areas (no per-manager area assignment in app). Use all area IDs so counts match dashboard/teams.
        $areaIds = Area::pluck('id')->toArray();
        $now = Carbon::now();

        if (empty($areaIds)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'period' => $period,
                    'visits' => 0,
                    'completion_percent' => 0,
                    'avg_time_minutes' => 0,
                    'active_teams' => 0,
                    'weekly_trend' => [],
                    'top_teams' => [],
                ],
            ]);
        }

        if ($period === 'today') {
            $start = $now->copy()->startOfDay();
            $end = $now;
        } elseif ($period === 'month') {
            $start = $now->copy()->startOfMonth();
            $end = $now;
        } else {
            $start = $now->copy()->startOfWeek();
            $end = $now;
        }

        // Count visits by scheduled_date in period only (no OR created_at) so each visit is counted once and "this week" = scheduled this week.
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();
        $startDt = $start->copy()->startOfDay();
        $endDt = $end->copy()->endOfDay();

        $visitQuery = Visit::query()
            ->whereIn('area_id', $areaIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate]);
        $visitsCount = (clone $visitQuery)->count();
        $completedCount = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
        $completionPercent = $visitsCount > 0 ? round(($completedCount / $visitsCount) * 100, 0) : 0;

        $completedVisits = Visit::query()
            ->whereIn('area_id', $areaIds)
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('started_at')
            ->whereNotNull('completed_at')
            ->get();
        $totalMinutes = $completedVisits->sum(fn ($v) => (int) $v->started_at->diffInMinutes($v->completed_at));
        $avgTimeMinutes = $completedVisits->isEmpty() ? 0 : (int) round($totalMinutes / $completedVisits->count());

        $activeTeamsCount = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->count('user_id');

        $weeklyTrend = [];
        if ($period === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $day = $now->copy()->subDays($i);
                $dayStr = $day->toDateString();
                $count = Visit::query()
                    ->whereIn('area_id', $areaIds)
                    ->whereDate('scheduled_date', $dayStr)
                    ->count();
                $weeklyTrend[] = ['date' => $dayStr, 'count' => $count];
            }
        } elseif ($period === 'month') {
            $monthStart = $now->copy()->startOfMonth();
            for ($d = $monthStart->copy(); $d->lte($now); $d->addDay()) {
                $dayStr = $d->toDateString();
                $count = Visit::query()
                    ->whereIn('area_id', $areaIds)
                    ->whereDate('scheduled_date', $dayStr)
                    ->count();
                $weeklyTrend[] = ['date' => $dayStr, 'count' => $count];
            }
        } else {
            $weeklyTrend[] = ['date' => $now->toDateString(), 'count' => $visitsCount];
        }

        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $topTeams = User::role('supervisor')->whereIn('id', $supervisorIds)->with('employee')->get()->map(function (User $u) use ($startDate, $endDate) {
            $uAreaIds = $u->supervisedAreaIds();
            $visits = empty($uAreaIds) ? 0 : Visit::whereIn('area_id', $uAreaIds)
                ->whereBetween('scheduled_date', [$startDate, $endDate])
                ->count();
            return [
                'id' => $u->id,
                'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                'name' => $u->name,
                'visits' => $visits,
                'rating' => 0, // placeholder: no rating table per supervisor
            ];
        })->sortByDesc('visits')->values()->take(10)->all();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'visits' => $visitsCount,
                'completion_percent' => $completionPercent,
                'avg_time_minutes' => $avgTimeMinutes,
                'active_teams' => $activeTeamsCount,
                'weekly_trend' => $weeklyTrend,
                'top_teams' => $topTeams,
            ],
        ]);
    }

    /**
     * GET /api/area-manager/reports
     * List visit reports for the region (reports submitted after visits in any area).
     * Each item = one Report (visit report) with visit and area info.
     */
    public function reportsIndex(Request $request): JsonResponse
    {
        $areaIds = Area::pluck('id')->toArray();
        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));

        $reports = Report::with(['visit.area', 'visit.technician:id,name', 'supervisor:id,name', 'supervisor.supervisedAreas:id,name'])
            ->whereHas('visit', function ($q) use ($areaIds) {
                if (empty($areaIds)) {
                    return;
                }
                $q->where(function ($q2) use ($areaIds) {
                    $q2->whereIn('area_id', $areaIds)->orWhereNull('area_id');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $list = $reports->getCollection()->map(function (Report $r) {
            $visit = $r->visit;
            $areaName = $visit?->area?->name;
            $areaId = $visit?->area_id;
            if ($areaName === null && $r->supervisor) {
                $firstArea = $r->supervisor->supervisedAreas->first();
                $areaName = $firstArea?->name ?? null;
                $areaId = $areaId ?? $firstArea?->id ?? null;
            }
            return [
                'id' => $r->id,
                'visit_id' => $r->visit_id,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
                'scheduled_date' => $visit?->scheduled_date?->toDateString(),
                'area_name' => $areaName,
                'area_id' => $areaId,
                'technician_name' => $visit?->technician?->name ?? null,
                'supervisor_name' => $r->supervisor?->name ?? null,
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $list,
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * POST /api/area-manager/reports/generate
     * Form-data or JSON: type (weekly_summary|team_performance|customer_satisfaction), date_from, date_to.
     * Creates AdminReport (pending), dispatches GenerateReportJob. Report file generated in background.
     *
     * Report content is built automatically from DB:
     * - Weekly Summary: period ke andar kitni jobs (visits) complete hui, kitna revenue generate hua (completed visits ka price sum), completion %, by day, by area.
     * - Team Performance: team leader (supervisor) ke hisaab se — har supervisor ki performance %, team size, active/done.
     * - Customer Satisfaction: jinhone feedback nahi bheja unki info + message.
     *
     * Generate PDF flow (where user goes when they click "Generate PDF"):
     * 1. User clicks "Generate PDF" → navigate to "Generate Report" screen (or open modal).
     * 2. On that screen: select report type (Weekly Summary | Team Performance | Customer Satisfaction) and date range (date_from, date_to).
     * 3. On submit → call this endpoint POST /api/area-manager/reports/generate.
     * 4. Show "Report is being generated. You will be notified when ready." and link/button to "Generated Reports".
     * 5. User goes to Generated Reports list (GET /api/area-manager/generated-reports) to see status and download when status = generated (download_url).
     */
    public function reportGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:weekly_summary,team_performance,customer_satisfaction',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $type = $request->input('type');
        $dateFrom = $request->input('date_from') ?? Carbon::now()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to') ?? Carbon::now()->endOfMonth()->toDateString();

        $adminReportType = match ($type) {
            'weekly_summary' => 'operational',
            'team_performance' => 'performance',
            'customer_satisfaction' => 'customer',
            default => 'operational',
        };
        $title = ucfirst(str_replace('_', ' ', $type)) . ' (' . $dateFrom . ' to ' . $dateTo . ')';

        $report = AdminReport::create([
            'title' => $title,
            'type' => $adminReportType,
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [
                'start_date' => $dateFrom,
                'end_date' => $dateTo,
                'format' => 'pdf',
            ],
            'created_by' => $request->user()->id,
        ]);

        GenerateReportJob::dispatch($report);

        return response()->json([
            'success' => true,
            'message' => 'Report generation started. You will be notified when it\'s ready. Check generated reports list for status.',
            'data' => [
                'id' => $report->id,
                'title' => $report->title,
                'status' => $report->status,
                'created_at' => $report->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * GET /api/area-manager/generated-reports
     * List reports generated by this Area Manager (Region Reports / Recent Reports).
     * This is the screen where user goes after "Generate PDF" to see status and download (download_url when status = generated).
     * Response shaped for UI: title, report_type, period, file_size, download_url, share_url.
     */
    public function generatedReportsIndex(Request $request): JsonResponse
    {
        $reports = AdminReport::where('created_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AdminReport $r) {
                $params = $r->parameters ?? [];
                $start = isset($params['start_date']) ? Carbon::parse($params['start_date']) : null;
                $end = isset($params['end_date']) ? Carbon::parse($params['end_date']) : null;
                $period = $this->formatReportPeriod($start, $end);
                $reportType = $this->reportTypeDisplayName($r->type, $r->title);
                $fileSizeFormatted = $r->file_size !== null ? $this->formatFileSize((int) $r->file_size) : null;
                $baseUrl = $r->status === 'generated' && $r->file_path
                    ? url('/api/area-manager/generated-reports/' . $r->id . '/download')
                    : null;

                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'report_type' => $reportType,
                    'type' => $r->type,
                    'period' => $period,
                    'file_size' => $fileSizeFormatted,
                    'status' => $r->status,
                    'generated_at' => $r->generated_at?->toIso8601String(),
                    'created_at' => $r->created_at?->toIso8601String(),
                    'download_url' => $baseUrl,
                    'share_url' => $baseUrl,
                ];
            })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $reports,
            'meta' => ['total' => count($reports)],
        ]);
    }

    private function formatReportPeriod(?Carbon $start, ?Carbon $end): ?string
    {
        if (! $start && ! $end) {
            return null;
        }
        if (! $end) {
            return $start->format('M j, Y');
        }
        if (! $start) {
            return $end->format('M j, Y');
        }
        $sameMonth = $start->month === $end->month && $start->year === $end->year;
        if ($sameMonth) {
            return $start->format('M j') . ' - ' . $end->format('j, Y');
        }
        $sameYear = $start->year === $end->year;
        if ($sameYear) {
            return $start->format('M j') . ' - ' . $end->format('M j, Y');
        }
        return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
    }

    private function reportTypeDisplayName(string $type, string $title): string
    {
        $map = [
            'operational' => 'Weekly Summary',
            'performance' => 'Team Performance',
            'customer' => 'Customer Satisfaction',
            'financial' => 'Financial',
            'user' => 'User',
            'subscription' => 'Subscription',
        ];
        if (isset($map[$type])) {
            return $map[$type];
        }
        if (preg_match('/^(\w[\w\s]+?)\s*\(/u', $title, $m)) {
            return trim($m[1]);
        }
        return $title;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * GET /api/area-manager/generated-reports/{id}/download
     * Download generated report file (only own reports, only when status = generated).
     */
    public function generatedReportDownload(Request $request, int $id)
    {
        $report = AdminReport::where('created_by', $request->user()->id)->findOrFail($id);
        if ($report->status !== 'generated' || ! $report->file_path) {
            return response()->json([
                'success' => false,
                'message' => 'Report file is not available yet or generation failed.',
            ], 404);
        }
        if (! Storage::disk('local')->exists($report->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Report file not found.',
            ], 404);
        }

        $ext = pathinfo($report->file_path, PATHINFO_EXTENSION) ?: $report->format;
        $mime = match (strtolower($ext)) {
            'csv' => 'text/csv',
            'txt' => 'text/plain',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/pdf',
        };
        $filename = 'area-manager-report-' . $report->id . '.' . $ext;

        return Storage::disk('local')->download(
            $report->file_path,
            $filename,
            ['Content-Type' => $mime]
        );
    }

    /**
     * GET /api/area-manager/profile
     * Area Manager profile: name, email, phone, id, profile_picture_url, rating, jobs_completed, total_earnings, member_since, specializations, service_area.
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
                'id' => $user->employee?->employee_id ?? ('AM-' . $user->id),
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
     * PUT or POST /api/area-manager/profile
     * Form-data only: name, email, phone, profile_picture (file). All optional. No password fields.
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
                'id' => $user->employee?->employee_id ?? ('AM-' . $user->id),
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->employee?->phone,
                'profile_picture' => $user->profile_picture,
                'profile_picture_url' => ProfilePictureUploadService::fullUrl($user->profile_picture),
            ],
        ]);
    }
}
