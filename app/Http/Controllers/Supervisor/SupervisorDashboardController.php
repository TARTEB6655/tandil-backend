<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SupervisorDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:supervisor']);
    }

    /**
     * Show the supervisor dashboard view.
     */
    public function index(): View
    {
        // Example data: pending reports for the supervisor's team
        $pendingReports = \App\Models\Report::where('status', 'pending')->count();
        $recentReports = \App\Models\Report::latest()->take(8)->get();

        return view('supervisor.dashboard', compact('pendingReports', 'recentReports'));
    }
}
