<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HrDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(Request $request): View
    {
        $user = Auth::user();
        $search = $request->get('search', '');

        // Get employee statistics
        $totalEmployees = Employee::count();
        $totalTechnicians = User::where('role', 'technician')->count();
        $totalSupervisors = User::where('role', 'supervisor')->count();
        $totalAreaManagers = User::where('role', 'area_manager')->count();
        
        // Employees by designation
        $employeesByDesignation = Employee::select('designation', \DB::raw('count(*) as count'))
            ->whereNotNull('designation')
            ->groupBy('designation')
            ->get()
            ->pluck('count', 'designation')
            ->toArray();

        // Recent employees
        $recentEmployees = Employee::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Employees by region
        $employeesByRegion = Employee::select('region', \DB::raw('count(*) as count'))
            ->whereNotNull('region')
            ->groupBy('region')
            ->get()
            ->pluck('count', 'region')
            ->toArray();

        return view('hr.dashboard', compact(
            'totalEmployees',
            'totalTechnicians',
            'totalSupervisors',
            'totalAreaManagers',
            'employeesByDesignation',
            'employeesByRegion',
            'recentEmployees',
            'search'
        ));
    }
}
