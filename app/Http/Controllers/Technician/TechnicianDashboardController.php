<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Visit;
use App\Models\Report;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TechnicianDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician']);
    }

    /**
     * Show the technician dashboard.
     */
    public function index(\Illuminate\Http\Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // Statistics - All visits assigned to this technician
        $totalVisits = Visit::where('technician_id', $user->id)->count();
        $completedVisits = Visit::where('technician_id', $user->id)
            ->where('status', 'completed')
            ->count();
        $pendingVisits = Visit::where('technician_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $acceptedVisits = Visit::where('technician_id', $user->id)
            ->where('status', 'accepted')
            ->count();
        $inProgressVisits = Visit::where('technician_id', $user->id)
            ->where('status', 'started')
            ->count();
        
        // Reports - Reports for visits assigned to this technician
        $visitIds = Visit::where('technician_id', $user->id)->pluck('id');
        $totalReports = Report::whereIn('visit_id', $visitIds)->count();
        $approvedReports = Report::whereIn('visit_id', $visitIds)
            ->where('status', 'approved')
            ->count();
        $pendingReports = Report::whereIn('visit_id', $visitIds)
            ->where('status', 'pending')
            ->count();
        
        // Complaints - Complaints for visits assigned to this technician
        $totalComplaints = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
        })->count();
        $resolvedComplaints = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
        })->where('status', 'resolved')->count();
        $pendingComplaints = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
        })->where('status', 'pending')->count();

        // Visits by Status Chart Data
        $visitsByStatus = Visit::where('technician_id', $user->id)
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
            $count = Visit::where('technician_id', $user->id)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $monthlyVisits[] = [
                'month' => $date->format('M Y'),
                'count' => $count
            ];
        }

        // Recent Visits
        $recentVisitsQuery = Visit::where('technician_id', $user->id);
        if ($search) {
            $recentVisitsQuery->where(function($q) use ($search) {
                $q->where('status', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('subscription.client', function($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('supervisor', function($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }
        $recentVisits = $recentVisitsQuery->with(['subscription.client', 'supervisor', 'area'])
            ->orderBy('scheduled_date', 'desc')
            ->take(5)
            ->get();
        
        // Recent Reports
        $recentReportsQuery = Report::whereIn('visit_id', $visitIds);
        if ($search) {
            $recentReportsQuery->where(function($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhere('technician_notes', 'LIKE', "%{$search}%")
                  ->orWhere('supervisor_notes', 'LIKE', "%{$search}%");
            });
        }
        $recentReports = $recentReportsQuery->with(['visit.subscription.client', 'supervisor'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();
        
        // Recent Complaints
        $recentComplaintsQuery = Complaint::whereHas('visit', function($q) use ($user) {
            $q->where('technician_id', $user->id);
        });
        if ($search) {
            $recentComplaintsQuery->where('notes', 'LIKE', "%{$search}%");
        }
        $recentComplaints = $recentComplaintsQuery->with(['visit.subscription.client'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('technician.dashboard', compact(
            'totalVisits',
            'completedVisits',
            'pendingVisits',
            'acceptedVisits',
            'inProgressVisits',
            'totalReports',
            'approvedReports',
            'pendingReports',
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
