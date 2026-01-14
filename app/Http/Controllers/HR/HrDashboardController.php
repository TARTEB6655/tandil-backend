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
        try {
            $user = Auth::user();
            $search = $request->get('search', '');

            // Get employee statistics
            $totalEmployees = Employee::count();
            $totalTechnicians = User::where('role', 'technician')->count();
            $totalSupervisors = User::where('role', 'supervisor')->count();
            $totalAreaManagers = User::where('role', 'area_manager')->count();
            
            // Employees by designation - handle case where designation column might not exist
            try {
                $employeesByDesignation = Employee::select('designation', DB::raw('count(*) as count'))
                    ->whereNotNull('designation')
                    ->groupBy('designation')
                    ->get()
                    ->pluck('count', 'designation')
                    ->toArray();
            } catch (\Exception $e) {
                $employeesByDesignation = [];
            }

            // Recent employees - handle case where created_at might not exist
            try {
                $recentEmployees = Employee::with('user')
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
            } catch (\Exception $e) {
                // Fallback: try without created_at
                $recentEmployees = Employee::with('user')
                    ->limit(5)
                    ->get();
            }

            // Employees by region - handle case where region column might not exist
            try {
                $employeesByRegion = Employee::select('region', DB::raw('count(*) as count'))
                    ->whereNotNull('region')
                    ->groupBy('region')
                    ->get()
                    ->pluck('count', 'region')
                    ->toArray();
            } catch (\Exception $e) {
                $employeesByRegion = [];
            }

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
        } catch (\Exception $e) {
            // Log the error and return a safe view with default values
            \Log::error('HR Dashboard Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return view('hr.dashboard', [
                'totalEmployees' => 0,
                'totalTechnicians' => 0,
                'totalSupervisors' => 0,
                'totalAreaManagers' => 0,
                'employeesByDesignation' => [],
                'employeesByRegion' => [],
                'recentEmployees' => collect(),
                'search' => $request->get('search', '')
            ]);
        }
    }
}
