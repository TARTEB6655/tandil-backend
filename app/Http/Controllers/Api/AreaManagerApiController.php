<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Region alerts (warning/success, message, timestamp). Placeholder until alerts table exists.
     */
    public function dashboardAlerts(Request $request): JsonResponse
    {
        $alerts = [];
        // Placeholder: no region_alerts table yet. Can be extended later.
        return response()->json(['success' => true, 'data' => $alerts]);
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
            $visitQuery = Visit::whereIn('area_id', $areaIds);
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
        $visitQuery = Visit::whereIn('area_id', $areaIds);
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
     * Recent region reports (placeholder until report storage exists).
     */
    public function reportsIndex(Request $request): JsonResponse
    {
        $list = [];
        return response()->json(['success' => true, 'data' => $list]);
    }

    /**
     * POST /api/area-manager/reports/generate
     * Generate region report (stub: returns message; PDF generation can be added later).
     */
    public function reportGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|string|in:weekly_summary,team_performance,customer_satisfaction',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Report generation requested. Feature can be extended to generate PDF.',
            'data' => ['id' => null, 'status' => 'pending'],
        ], 202);
    }

    /**
     * GET /api/area-manager/profile
     * Area Manager profile: name, email, phone, id, rating, jobs_completed, total_earnings, member_since, specializations, service_area.
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
     * PUT /api/area-manager/profile
     * Update Area Manager profile (name, email, phone).
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $rules = [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:50',
        ];
        $data = $request->validate($rules);
        if (! empty($data['name'])) {
            $user->name = $data['name'];
        }
        if (! empty($data['email'])) {
            $user->email = $data['email'];
        }
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
            if ($user->employee) {
                $user->employee->phone = $data['phone'];
                $user->employee->save();
            }
        }
        $user->save();
        $user->load('employee');
        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->employee?->phone,
                'id' => $user->employee?->employee_id ?? ('AM-' . $user->id),
            ],
        ]);
    }
}
