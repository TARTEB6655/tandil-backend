<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Visit;
use App\Models\Report;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SupervisorDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    /**
     * Same visit scope as API: visits in supervisor's areas OR assigned to supervisor (supervisor_id = me).
     */
    private function visitsQuery()
    {
        $user = Auth::user();
        $areaIds = $user->supervisedAreaIds();
        $supervisorId = $user->id;
        return Visit::query()->where(function ($q) use ($areaIds, $supervisorId) {
            if (! empty($areaIds)) {
                $q->whereIn('area_id', $areaIds);
            }
            $q->orWhere('supervisor_id', $supervisorId);
        });
    }

    /**
     * Assignable visits (unassigned, pending/scheduled, or escalated) – same as API assignableVisitsQuery.
     */
    private function assignableVisitsQuery()
    {
        return $this->visitsQuery()->where(function ($q) {
            $q->whereNull('technician_id')
                ->orWhereIn('status', ['pending', 'scheduled'])
                ->orWhereNotNull('escalated_at');
        });
    }

    private function teamMemberIdsInZones(array $areaIds): \Illuminate\Support\Collection
    {
        if (empty($areaIds)) {
            return collect();
        }
        return collect(DB::table('area_technician')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id'));
    }

    /**
     * Show the supervisor dashboard. Data aligned with API: dashboard/summary, dashboard/kpis, dashboard/alerts.
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');
        $areaIds = $user->supervisedAreaIds();

        // ---- API-aligned summary (GET /api/supervisor/dashboard/summary) ----
        $teamMembers = $this->teamMemberIdsInZones($areaIds)->count();
        $query = $this->visitsQuery();
        $activeVisits = (clone $query)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
        $completedVisits = (clone $query)->whereIn('status', ['completed', 'approved'])->count();
        $escalatedJobs = $this->assignableVisitsQuery()->whereNotNull('escalated_at')->count();

        // ---- API-aligned KPIs (GET /api/supervisor/dashboard/kpis) ----
        $today = Carbon::today();
        $completedToday = (clone $query)->whereDate('completed_at', $today)->count();
        $totalVisits = (clone $query)->count();
        $completionRatePercent = $totalVisits > 0 ? round(($completedVisits / $totalVisits) * 100, 2) : 0;

        // ---- API-aligned alerts (GET /api/supervisor/dashboard/alerts) ----
        $alerts = [];
        $tomorrow = Carbon::tomorrow();
        $overdue = (clone $query)
            ->whereDate('scheduled_date', '<', $today)
            ->whereNotIn('status', ['completed', 'approved', 'rejected'])
            ->count();
        if ($overdue > 0) {
            $alerts[] = ['type' => 'overdue_visits', 'count' => $overdue, 'message' => "{$overdue} visits are overdue."];
        }
        $upcoming = (clone $query)->whereDate('scheduled_date', $tomorrow)->count();
        if ($upcoming > 0) {
            $alerts[] = ['type' => 'upcoming_visits', 'count' => $upcoming, 'message' => "{$upcoming} visits scheduled for tomorrow."];
        }

        // Visit IDs in scope (for reports/complaints)
        $visitIds = $this->visitsQuery()->pluck('id');

        if ($visitIds->isEmpty() && empty($areaIds)) {
            return view('supervisor.dashboard', [
                'teamMembers' => 0,
                'activeVisits' => 0,
                'completedVisits' => 0,
                'escalatedJobs' => 0,
                'completedToday' => 0,
                'completionRatePercent' => 0,
                'alerts' => [],
                'totalVisits' => 0,
                'pendingReports' => 0,
                'approvedReports' => 0,
                'totalReports' => 0,
                'totalComplaints' => 0,
                'resolvedComplaints' => 0,
                'pendingComplaints' => 0,
                'visitsByStatus' => [],
                'monthlyVisits' => [],
                'recentVisits' => collect(),
                'recentReports' => collect(),
                'recentComplaints' => collect(),
            ]);
        }

        // Reports for visits in scope (same as API report scope)
        $totalReports = Report::whereIn('visit_id', $visitIds)->count();
        $pendingReports = Report::whereIn('visit_id', $visitIds)->where('status', 'pending')->count();
        $approvedReports = Report::whereIn('visit_id', $visitIds)->where('status', 'approved')->count();

        // Complaints for visits in scope
        $totalComplaints = Complaint::whereIn('visit_id', $visitIds)->count();
        $resolvedComplaints = Complaint::whereIn('visit_id', $visitIds)->where('status', 'resolved')->count();
        $pendingComplaints = Complaint::whereIn('visit_id', $visitIds)->where('status', 'pending')->count();

        // Visits by Status (for chart)
        $visitsByStatus = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => ['status' => $item->status, 'count' => $item->count])
            ->toArray();

        // Monthly Visits (last 6 months)
        $monthlyVisits = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = (clone $query)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $monthlyVisits[] = ['month' => $date->format('M Y'), 'count' => $count];
        }

        // Recent Visits (same scope)
        $recentVisitsQuery = (clone $query)->with(['subscription.client', 'technician', 'area']);
        if ($search) {
            $recentVisitsQuery->where(function ($q) use ($search) {
                $q->where('status', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('subscription.client', fn ($cq) => $cq->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereHas('technician', fn ($tq) => $tq->where('name', 'LIKE', "%{$search}%"));
            });
        }
        $recentVisits = $recentVisitsQuery->orderByDesc('scheduled_date')->take(5)->get();

        // Recent Reports
        $recentReportsQuery = Report::whereIn('visit_id', $visitIds)->with(['visit.subscription.client', 'supervisor']);
        if ($search) {
            $recentReportsQuery->where(function ($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                    ->orWhere('technician_notes', 'LIKE', "%{$search}%")
                    ->orWhere('supervisor_notes', 'LIKE', "%{$search}%")
                    ->orWhereHas('visit.subscription.client', fn ($cq) => $cq->where('name', 'LIKE', "%{$search}%"));
            });
        }
        $recentReports = $recentReportsQuery->orderByDesc('created_at')->take(5)->get();

        // Recent Complaints
        $recentComplaintsQuery = Complaint::whereIn('visit_id', $visitIds)->with(['visit.subscription.client']);
        if ($search) {
            $recentComplaintsQuery->where(function ($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                    ->orWhere('status', 'LIKE', "%{$search}%")
                    ->orWhereHas('visit.subscription.client', fn ($cq) => $cq->where('name', 'LIKE', "%{$search}%"));
            });
        }
        $recentComplaints = $recentComplaintsQuery->orderByDesc('created_at')->take(5)->get();

        return view('supervisor.dashboard', compact(
            'teamMembers',
            'activeVisits',
            'completedVisits',
            'escalatedJobs',
            'completedToday',
            'completionRatePercent',
            'alerts',
            'totalVisits',
            'pendingReports',
            'approvedReports',
            'totalReports',
            'totalComplaints',
            'resolvedComplaints',
            'pendingComplaints',
            'visitsByStatus',
            'monthlyVisits',
            'recentVisits',
            'recentReports',
            'recentComplaints'
        ));
    }
}
