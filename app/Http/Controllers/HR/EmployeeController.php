<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class EmployeeController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:hr']);
    }

    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        
        $employeesQuery = Employee::with('user');
        
        if ($search) {
            $employeesQuery->where(function($q) use ($search) {
                $q->where('employee_id', 'LIKE', "%{$search}%")
                  ->orWhere('designation', 'LIKE', "%{$search}%")
                  ->orWhere('region', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search) {
                      $uq->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                  });
            });
        }
        
        $employees = $employeesQuery->orderBy('created_at', 'desc')->paginate(15);

        return view('hr.employees.index', compact('employees', 'search'));
    }

    public function create(): View
    {
        // Get users without employee records
        $availableUsers = User::whereDoesntHave('employee')
            ->whereIn('role', ['technician', 'supervisor', 'area_manager'])
            ->orderBy('name')
            ->get();

        return view('hr.employees.create', compact('availableUsers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id|unique:employees,user_id',
            'employee_id' => 'required|string|unique:employees,employee_id',
            'phone' => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
        ]);

        Employee::create($validated);

        return redirect()->route('hr.employees.index')
            ->with('success', 'Employee record created successfully.');
    }

    public function show($id): View
    {
        $employee = Employee::with('user')->findOrFail($id);

        // Get performance statistics if user is linked
        $performanceStats = null;
        if ($employee->user) {
            $user = $employee->user;
            
            // If technician - get visit stats
            if ($user->role === 'technician') {
                $totalVisits = \App\Models\Visit::where('technician_id', $user->id)->count();
                $completedVisits = \App\Models\Visit::where('technician_id', $user->id)
                    ->where('status', 'completed')->count();
                $visitIds = \App\Models\Visit::where('technician_id', $user->id)->pluck('id');
                $totalReports = \App\Models\Report::whereIn('visit_id', $visitIds)->count();
                
                $performanceStats = [
                    'total_visits' => $totalVisits,
                    'completed_visits' => $completedVisits,
                    'total_reports' => $totalReports,
                    'type' => 'technician'
                ];
            }
            
            // If supervisor - get area and report stats
            if ($user->role === 'supervisor') {
                $areaIds = $user->supervisedAreas()->pluck('areas.id')->toArray();
                $totalVisits = \App\Models\Visit::whereIn('area_id', $areaIds)->count();
                $visitIds = \App\Models\Visit::whereIn('area_id', $areaIds)->pluck('id');
                $totalReports = \App\Models\Report::whereIn('visit_id', $visitIds)->count();
                $approvedReports = \App\Models\Report::whereIn('visit_id', $visitIds)
                    ->where('status', 'approved')->count();
                
                $performanceStats = [
                    'total_visits' => $totalVisits,
                    'total_reports' => $totalReports,
                    'approved_reports' => $approvedReports,
                    'supervised_areas' => count($areaIds),
                    'type' => 'supervisor'
                ];
            }
        }

        return view('hr.employees.show', compact('employee', 'performanceStats'));
    }

    public function edit($id): View
    {
        $employee = Employee::with('user')->findOrFail($id);

        return view('hr.employees.edit', compact('employee'));
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|string|unique:employees,employee_id,' . $id,
            'phone' => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
        ]);

        $employee->update($validated);

        return redirect()->route('hr.employees.index')
            ->with('success', 'Employee record updated successfully.');
    }

    public function destroy($id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return redirect()->route('hr.employees.index')
            ->with('success', 'Employee record deleted successfully.');
    }

    public function createUser(Request $request, $id): RedirectResponse
    {
        $employee = Employee::findOrFail($id);
        
        if ($employee->user_id) {
            return back()->with('error', 'Employee already has a linked user account.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:technician,supervisor,area_manager',
            'status' => 'required|in:active,inactive',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        $user->assignRole($validated['role']);

        // Link user to employee
        $employee->user_id = $user->id;
        $employee->save();

        return redirect()->route('hr.employees.show', $employee->id)
            ->with('success', 'User account created and linked to employee successfully.');
    }

    public function updateUserStatus(Request $request, $id): RedirectResponse
    {
        $employee = Employee::with('user')->findOrFail($id);
        
        if (!$employee->user) {
            return back()->with('error', 'Employee does not have a linked user account.');
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        $employee->user->status = $validated['status'];
        $employee->user->save();

        return back()->with('success', 'User account status updated successfully.');
    }
}
