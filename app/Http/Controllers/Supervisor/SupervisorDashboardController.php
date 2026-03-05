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
     * Show the supervisor dashboard view.
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // Get IDs of supervised areas
        $areaIds = $user->supervisedAreaIds();

        if (empty($areaIds)) {
            // If supervisor has no areas, return empty dashboard
            return view('supervisor.dashboard', [
                'totalVisits' => 0,
                'completedVisits' => 0,
                'pendingVisits' => 0,
                'totalReports' => 0,
                'pendingReports' => 0,
                'approvedReports' => 0,
                'totalComplaints' => 0,
                'resolvedComplaints' => 0,
                'pendingComplaints' => 0,
                'teamMembers' => 0,
                'escalatedJobs' => 0,
                'visitsByStatus' => [],
                'monthlyVisits' => [],
                'recentVisits' => collect(),
                'recentReports' => collect(),
                'recentComplaints' => collect(),
            ]);
        }

        // Statistics - Visits in supervised areas
        $totalVisits = Visit::whereIn('area_id', $areaIds)->count();
        $completedVisits = Visit::whereIn('area_id', $areaIds)
            ->where('status', 'completed')
            ->count();
        $pendingVisits = Visit::whereIn('area_id', $areaIds)
            ->where('status', 'pending')
            ->count();
        
        // Reports for visits in supervised areas
        $visitIds = Visit::whereIn('area_id', $areaIds)->pluck('id');
        $totalReports = Report::whereIn('visit_id', $visitIds)->count();
        $pendingReports = Report::whereIn('visit_id', $visitIds)
            ->where('status', 'pending')
            ->count();
        $approvedReports = Report::whereIn('visit_id', $visitIds)
            ->where('status', 'approved')
            ->count();
        
        // Complaints for visits in supervised areas
        $totalComplaints = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })->count();
        $resolvedComplaints = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })->where('status', 'resolved')->count();
        $pendingComplaints = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        })->where('status', 'pending')->count();

        // Visits by Status Chart Data
        $visitsByStatus = Visit::whereIn('area_id', $areaIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function($item) {
                return [
                    'status' => $item->status,
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Monthly Visits Chart Data (Last 6 Months)
        $monthlyVisits = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $count = Visit::whereIn('area_id', $areaIds)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $monthlyVisits[] = [
                'month' => $date->format('M Y'),
                'count' => $count
            ];
        }

        // Recent Visits
        $recentVisitsQuery = Visit::whereIn('area_id', $areaIds);
        if ($search) {
            $recentVisitsQuery->where(function($q) use ($search) {
                $q->where('status', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('technician', function($tq) use ($search) {
                      $tq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        $recentVisits = $recentVisitsQuery->with(['subscription.client', 'technician', 'area'])
            ->orderBy('scheduled_date', 'desc')
            ->take(5)
            ->get();
        
        // Recent Reports
        $recentReportsQuery = Report::whereIn('visit_id', $visitIds);
        if ($search) {
            $recentReportsQuery->where(function($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('technician_notes', 'LIKE', "%{$search}%")
                  ->orWhere('supervisor_notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('visit.subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        $recentReports = $recentReportsQuery->with(['visit.subscription.client', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Recent Complaints
        $recentComplaintsQuery = Complaint::whereHas('visit', function($q) use ($areaIds) {
            $q->whereIn('area_id', $areaIds);
        });
        if ($search) {
            $recentComplaintsQuery->where(function($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('status', 'LIKE', "%{$search}%")
                  ->orWhereHas('visit.subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        $recentComplaints = $recentComplaintsQuery->with(['visit.subscription.client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $teamMembers = (int) \Illuminate\Support\Facades\DB::table('area_technician')
            ->whereIn('area_id', $areaIds)
            ->distinct()
            ->count('user_id');
        $escalatedJobs = Visit::whereIn('area_id', $areaIds)
            ->whereNotNull('escalated_at')
            ->count();

        return view('supervisor.dashboard', compact(
            'totalVisits',
            'completedVisits',
            'pendingVisits',
            'totalReports',
            'pendingReports',
            'approvedReports',
            'totalComplaints',
            'resolvedComplaints',
            'pendingComplaints',
            'teamMembers',
            'escalatedJobs',
            'visitsByStatus',
            'monthlyVisits',
            'recentVisits',
            'recentReports',
            'recentComplaints'
        ));
    }
}
