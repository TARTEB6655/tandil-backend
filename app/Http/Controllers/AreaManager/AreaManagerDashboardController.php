<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Report;
use App\Models\Subscription;
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

    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // Get all areas
        $totalAreas = Area::count();
        $totalSupervisors = User::where('role', 'supervisor')->count();
        $totalTechnicians = User::where('role', 'technician')->count();
        
        // Get all visits
        $totalVisits = Visit::count();
        $pendingVisits = Visit::where('status', 'pending')->count();
        $acceptedVisits = Visit::where('status', 'accepted')->count();
        $inProgressVisits = Visit::where('status', 'started')->count();
        $completedVisits = Visit::where('status', 'completed')->count();
        $approvedVisits = Visit::where('status', 'approved')->count();
        
        // Get all reports
        $totalReports = Report::count();
        $pendingReports = Report::where('status', 'pending')->count();
        $approvedReports = Report::where('status', 'approved')->count();

        // Visits by status for chart
        $visitsByStatus = [
            'Pending' => Visit::where('status', 'pending')->count(),
            'Accepted' => Visit::where('status', 'accepted')->count(),
            'In Progress' => Visit::where('status', 'started')->count(),
            'Completed' => Visit::where('status', 'completed')->count(),
            'Approved' => Visit::where('status', 'approved')->count(),
        ];
        
        // Convert to array format for chart
        $visitsByStatusData = array_values($visitsByStatus);
        $visitsByStatusLabels = array_keys($visitsByStatus);

        // Monthly visits for chart (last 6 months)
        $monthlyVisits = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthlyVisits[] = [
                'month' => $month->format('M Y'),
                'count' => Visit::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->count()
            ];
        }

        // Recent visits
        $recentVisits = Visit::with(['subscription.client', 'technician', 'area'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Recent reports
        $recentReports = Report::with(['visit.subscription.client', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Team leaders (supervisors) with performance %
        $areaIds = Area::pluck('id')->toArray();
        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $teamLeaders = User::role('supervisor')
            ->whereIn('id', $supervisorIds)
            ->with('employee')
            ->get()
            ->map(function (User $u) {
                $areaIds = $u->supervisedAreaIds();
                $visitQuery = Visit::where(function ($q) use ($u, $areaIds) {
                    $q->where('supervisor_id', $u->id);
                    if (! empty($areaIds)) {
                        $q->orWhereIn('area_id', $areaIds);
                    }
                });
                $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
                $done = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
                $total = $active + $done;
                $performance = $total > 0 ? round(($done / $total) * 100, 0) : 0;
                $teamCount = empty($areaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->count('user_id');
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

        // Alerts (overdue, expiring subscriptions, stuck visits)
        $today = Carbon::today();
        $alerts = [];
        $overdueCount = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['pending', 'scheduled', 'in_progress'])
            ->where('scheduled_date', '<', $today)
            ->count();
        if ($overdueCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $overdueCount === 1 ? '1 visit is overdue.' : "{$overdueCount} visits are overdue."];
        }
        $expiringCount = Subscription::where('payment_status', 'paid')
            ->whereBetween('end_date', [$today, $today->copy()->addDays(7)])
            ->count();
        if ($expiringCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $expiringCount === 1 ? '1 subscription expiring in 7 days.' : "{$expiringCount} subscriptions expiring in 7 days."];
        }
        $stuckCount = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['in_progress', 'started'])
            ->whereNotNull('started_at')
            ->where('started_at', '<', Carbon::now()->subHours(24))
            ->count();
        if ($stuckCount > 0) {
            $alerts[] = ['type' => 'warning', 'message' => $stuckCount === 1 ? '1 visit in progress over 24 hours.' : "{$stuckCount} visits in progress over 24 hours."];
        }
        if (empty($alerts)) {
            $alerts[] = ['type' => 'info', 'message' => 'No alerts. All visits and subscriptions are on track.'];
        }

        return view('areamanager.dashboard', compact(
            'totalAreas',
            'totalSupervisors',
            'totalTechnicians',
            'totalVisits',
            'pendingVisits',
            'acceptedVisits',
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
