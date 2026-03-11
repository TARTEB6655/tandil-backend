<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Order;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\TechnicianVacation;
use App\Models\Visit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AreaManagerDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:area_manager']);
    }

    /**
     * Same scope as API: all areas (area manager sees all). Visit/report/subscription stats scoped to areaIds.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // API-aligned: all area IDs (area manager sees all areas)
        $areaIds = Area::pluck('id')->toArray();
        $visitQuery = empty($areaIds) ? Visit::query()->whereRaw('1 = 0') : Visit::whereIn('area_id', $areaIds);

        // ---- API-aligned summary (GET /api/area-manager/dashboard/summary) ----
        $totalFarms = Area::count();
        $totalAreas = $totalFarms;
        $activeSubscriptions = Subscription::where('payment_status', 'paid')
            ->where('end_date', '>=', Carbon::today())
            ->count();
        $monthlyRevenue = (float) Order::where('payment_status', 'paid')
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('total_amount');
        $teamCount = empty($areaIds) ? 0 : (int) DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
        $activeVisits = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
        $doneVisits = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();

        // Scoped totals and breakdown (for charts / secondary)
        $totalVisits = (clone $visitQuery)->count();
        $pendingVisits = (clone $visitQuery)->where('status', 'pending')->count();
        $scheduledVisits = (clone $visitQuery)->where('status', 'scheduled')->count();
        $inProgressVisits = (clone $visitQuery)->where('status', 'in_progress')->count();
        $completedVisits = (clone $visitQuery)->where('status', 'completed')->count();
        $approvedVisits = (clone $visitQuery)->where('status', 'approved')->count();

        $visitIds = (clone $visitQuery)->pluck('id');
        $totalReports = Report::whereIn('visit_id', $visitIds)->count();
        $pendingReports = Report::whereIn('visit_id', $visitIds)->where('status', 'pending')->count();
        $approvedReports = Report::whereIn('visit_id', $visitIds)->where('status', 'approved')->count();

        // Counts for team card (supervisors + technicians in areas)
        $totalSupervisors = $teamCount;
        $totalTechnicians = empty($areaIds) ? 0 : (int) DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->count('user_id');

        // Visits by status for chart (API statuses)
        $visitsByStatus = [
            'Pending' => $pendingVisits,
            'Scheduled' => $scheduledVisits,
            'In Progress' => $inProgressVisits,
            'Completed' => $completedVisits,
            'Approved' => $approvedVisits,
        ];
        $visitsByStatusData = array_values($visitsByStatus);
        $visitsByStatusLabels = array_keys($visitsByStatus);

        // Monthly visits (scoped to areas)
        $monthlyVisits = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyVisits[] = [
                'month' => $month->format('M Y'),
                'count' => (clone $visitQuery)->whereYear('created_at', $month->year)->whereMonth('created_at', $month->month)->count(),
            ];
        }

        // Recent visits (scoped)
        $recentVisits = (clone $visitQuery)->with(['subscription.client', 'technician', 'area'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent reports (scoped)
        $recentReports = Report::whereIn('visit_id', $visitIds)->with(['visit.subscription.client', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Team leaders (supervisors in areas) with performance %
        $supervisorIds = empty($areaIds) ? collect() : DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $teamLeaders = User::role('supervisor')
            ->whereIn('id', $supervisorIds)
            ->with('employee')
            ->get()
            ->map(function (User $u) {
                $supAreaIds = $u->supervisedAreaIds();
                $q = Visit::query()->where(function ($q) use ($u, $supAreaIds) {
                    $q->where('supervisor_id', $u->id);
                    if (! empty($supAreaIds)) {
                        $q->orWhereIn('area_id', $supAreaIds);
                    }
                });
                $active = (clone $q)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
                $done = (clone $q)->whereIn('status', ['completed', 'approved'])->count();
                $total = $active + $done;
                $performance = $total > 0 ? round(($done / $total) * 100, 0) : 0;
                $teamCount = empty($supAreaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $supAreaIds)->distinct()->count('user_id');
                $firstArea = $u->supervisedAreas()->first();
                return (object) [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee?->employee_id ?? ('SUP-' . $u->id),
                    'location' => $firstArea?->name ?? $firstArea?->location ?? null,
                    'performance_percent' => $performance,
                    'team' => $teamCount,
                    'active' => $active,
                    'done' => $done,
                ];
            })
            ->sortByDesc('performance_percent')
            ->take(5)
            ->values();

        // ---- API-aligned alerts (GET /api/area-manager/dashboard/alerts) ----
        $today = Carbon::today();
        $alerts = [];
        $overdueCount = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->where('scheduled_date', '<', $today)->count();
        if ($overdueCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $overdueCount === 1 ? '1 visit is overdue.' : "{$overdueCount} visits are overdue."];
        }
        $expiringCount = Subscription::where('payment_status', 'paid')->whereBetween('end_date', [$today, $today->copy()->addDays(7)])->count();
        if ($expiringCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $expiringCount === 1 ? '1 subscription is expiring in the next 7 days.' : "{$expiringCount} subscriptions are expiring in the next 7 days."];
        }
        $stuckCount = (clone $visitQuery)->where('status', 'in_progress')->whereNotNull('started_at')->where('started_at', '<', Carbon::now()->subHours(24))->count();
        if ($stuckCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $stuckCount === 1 ? '1 visit has been in progress for over 24 hours.' : "{$stuckCount} visits have been in progress for over 24 hours."];
        }
        $technicianIdsInRegion = empty($areaIds) ? collect() : DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $onLeaveToday = TechnicianVacation::whereIn('user_id', $technicianIdsInRegion)
            ->where('start_date', '<=', $today)
            ->where('end_date', '>=', $today)
            ->count();
        if ($onLeaveToday > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $onLeaveToday === 1 ? '1 worker is on leave today.' : "{$onLeaveToday} workers are on leave today."];
        }
        if (empty($alerts)) {
            $alerts[] = ['type' => 'info', 'message' => 'No alerts at this time. All visits and subscriptions are on track.'];
        }

        return view('areamanager.dashboard', compact(
            'totalFarms',
            'activeSubscriptions',
            'monthlyRevenue',
            'teamCount',
            'activeVisits',
            'doneVisits',
            'totalAreas',
            'totalSupervisors',
            'totalTechnicians',
            'totalVisits',
            'pendingVisits',
            'scheduledVisits',
            'inProgressVisits',
            'completedVisits',
            'approvedVisits',
            'totalReports',
            'pendingReports',
            'approvedReports',
            'visitsByStatus',
            'visitsByStatusLabels',
            'visitsByStatusData',
            'monthlyVisits',
            'recentVisits',
            'recentReports',
            'teamLeaders',
            'alerts',
            'search'
        ));
    }
}
