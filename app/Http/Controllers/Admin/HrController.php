<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;

class HrController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $query = Employee::with('user');

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('role', $request->role);
            });
        }

        // Search
        if ($request->has('search') && $request->search) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('email', 'LIKE', "%{$request->search}%");
            })->orWhere('employee_id', 'LIKE', "%{$request->search}%");
        }

        $employees = $query->orderBy('created_at', 'desc')->paginate(15);

        return view('admin.hr.index', compact('employees'));
    }

    public function create()
    {
        $users = User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])
            ->whereDoesntHave('employee')
            ->get();
        
        return view('admin.hr.create', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id|unique:employees,user_id',
            'employee_id' => 'nullable|string|max:255|unique:employees,employee_id',
            'phone' => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
        ]);

        // Generate employee ID if not provided
        if (!$request->employee_id) {
            $request->merge(['employee_id' => $this->generateEmployeeId()]);
        }

        Employee::create($request->all());

        return redirect()->route('admin.hr.index')
            ->with('success', 'Employee record created successfully');
    }

    public function show($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        return view('admin.hr.show', compact('employee'));
    }

    public function edit($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        return view('admin.hr.edit', compact('employee'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'employee_id' => 'nullable|string|max:255|unique:employees,employee_id,' . $id,
            'phone' => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
        ]);

        $employee = Employee::findOrFail($id);
        $employee->update($request->all());

        return redirect()->route('admin.hr.index')
            ->with('success', 'Employee record updated successfully');
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return redirect()->route('admin.hr.index')
            ->with('success', 'Employee record deleted successfully');
    }

    private function generateEmployeeId()
    {
        $prefix = 'EMP';
        $lastEmployee = Employee::orderBy('id', 'desc')->first();
        $number = $lastEmployee ? (int) substr($lastEmployee->employee_id, 3) + 1 : 1;
        return $prefix . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
}

