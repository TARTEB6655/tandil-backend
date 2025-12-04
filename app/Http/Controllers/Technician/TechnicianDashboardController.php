<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class TechnicianDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:technician']);
    }

    /**
     * Show the technician dashboard.
     */
    public function index(): View
    {
        // Example: show assigned jobs for the logged-in technician
        $user = Auth::user();
        $assignedJobs = \App\Models\Job::where('technician_id', $user->id)
                        ->whereIn('status', ['assigned','in_progress'])
                        ->orderBy('scheduled_at', 'asc')
                        ->take(20)->get();

        return view('technician.dashboard', compact('assignedJobs'));
    }
}
