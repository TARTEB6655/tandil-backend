<?php

namespace App\Http\Controllers\AreaManager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area;
use App\Models\Visit;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
            'search'
        ));
    }
}
