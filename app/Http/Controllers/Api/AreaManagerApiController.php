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
            // Count visits: in supervisor's areas OR directly assigned to this supervisor (supervisor_id)
            $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
                $q->where('supervisor_id', $u->id);
                if (! empty($areaIds)) {
                    $q->orWhereIn('area_id', $areaIds);
                }
            });
            $activeCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
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
        $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
            $q->where('supervisor_id', $u->id);
            if (! empty($areaIds)) {
                $q->orWhereIn('area_id', $areaIds);
            }
        });
        $activeCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
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
            'profile_picture_url' => ProfilePictureUploadService::fullUrl($u->profile_picture),
            'performance_percent' => $performance,
            'team' => $teamCount,
            'active' => $activeCount,
            'done' => $doneCount,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/area-manager/analytics?period=today|week|month
     * Visits, completion %, avg time, active teams, weekly_trend, top_teams.
     */
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'week');
        $areaIds = Area::pluck('id')->toArray();
        $now = Carbon::now();

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

        $visitQuery = Visit::whereIn('area_id', $areaIds)->whereBetween('created_at', [$start, $end]);
        $visitsCount = (clone $visitQuery)->count();
        $completedCount = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
        $completionPercent = $visitsCount > 0 ? round(($completedCount / $visitsCount) * 100, 0) : 0;

        $completedVisits = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['completed', 'approved'])
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
                $count = Visit::whereIn('area_id', $areaIds)
                    ->whereDate('created_at', $day)
                    ->count();
                $weeklyTrend[] = ['date' => $day->toDateString(), 'count' => $count];
            }
        } elseif ($period === 'month') {
            $monthStart = $now->copy()->startOfMonth();
            for ($d = $monthStart->copy(); $d->lte($now); $d->addDay()) {
                $count = Visit::whereIn('area_id', $areaIds)
                    ->whereDate('created_at', $d)
                    ->count();
                $weeklyTrend[] = ['date' => $d->toDateString(), 'count' => $count];
            }
        } else {
            $weeklyTrend[] = ['date' => $now->toDateString(), 'count' => $visitsCount];
        }

        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $topTeams = User::role('supervisor')->whereIn('id', $supervisorIds)->with('employee')->get()->map(function (User $u) use ($start, $end) {
            $areaIds = $u->supervisedAreaIds();
            $visits = Visit::whereIn('area_id', $areaIds)->whereBetween('created_at', [$start, $end])->count();
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

        $reports = Report::with(['visit.area', 'visit.technician:id,name', 'supervisor:id,name'])
            ->whereHas('visit', function ($q) use ($areaIds) {
                $q->whereIn('area_id', $areaIds);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $list = $reports->getCollection()->map(function (Report $r) {
            $visit = $r->visit;
            return [
                'id' => $r->id,
                'visit_id' => $r->visit_id,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
                'scheduled_date' => $visit?->scheduled_date?->toDateString(),
                'area_name' => $visit?->area?->name ?? null,
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
     * List reports generated by this Area Manager (AdminReport where created_by = current user).
     */
    public function generatedReportsIndex(Request $request): JsonResponse
    {
        $reports = AdminReport::where('created_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AdminReport $r) {
                return [
                    'id' => $r->id,
                    'title' => $r->title,
                    'type' => $r->type,
                    'status' => $r->status,
                    'generated_at' => $r->generated_at?->toIso8601String(),
                    'created_at' => $r->created_at?->toIso8601String(),
                    'download_url' => $r->status === 'generated' && $r->file_path
                        ? url('/api/area-manager/generated-reports/' . $r->id . '/download')
                        : null,
                ];
            })->values()->all();

        return response()->json([
            'success' => true,
            'data' => $reports,
            'meta' => ['total' => count($reports)],
        ]);
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
