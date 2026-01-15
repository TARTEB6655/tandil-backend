<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;

class EmployeeController extends Controller
{
    // Only users with 'manage employees' permission can access these routes
    public function __construct()
    {
        // Use 'auth' for web routes, 'auth:sanctum' for API routes
        $this->middleware(['auth', 'role:hr|admin']);
    }

    // Show list of employees
    public function index(Request $request)
    {
        try {
            $query = Employee::with('user');

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('employee_id', 'LIKE', "%{$search}%")
                      ->orWhere('designation', 'LIKE', "%{$search}%")
                      ->orWhere('region', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                $employees = $query->orderBy('created_at', 'desc')
                    ->get()
                    ->map(function ($employee) {
                        return [
                            'id' => $employee->id,
                            'user_id' => $employee->user_id,
                            'name' => $employee->name ?? $employee->user->name ?? null,
                            'email' => $employee->email ?? $employee->user->email ?? null,
                            'employee_id' => $employee->employee_id,
                            'phone' => $employee->phone ?? $employee->user->phone ?? null,
                            'designation' => $employee->designation,
                            'region' => $employee->region,
                            'joining_date' => $employee->joining_date,
                            'created_at' => $employee->created_at,
                            'updated_at' => $employee->updated_at,
                            'user' => $employee->user ? [
                                'id' => $employee->user->id,
                                'name' => $employee->user->name,
                                'email' => $employee->user->email,
                                'phone' => $employee->user->phone,
                                'role' => $employee->user->role,
                            ] : null,
                        ];
                    });
                
                return response()->json([
                    'success' => true,
                    'message' => 'Employees retrieved successfully',
                    'data' => $employees,
                    'total' => $employees->count()
                ], 200);
            }

            // Web request - return view with pagination
            $employees = $query->orderBy('created_at', 'desc')->paginate(15);
            $search = $request->get('search', '');

            return view('hr.employees.index', compact('employees', 'search'));
        } catch (\Exception $e) {
            // For API requests, return JSON error
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch employees: ' . $e->getMessage()
                ], 500);
            }

            // For web requests, redirect with error message
            return redirect()->route('hr.dashboard')
                ->with('error', 'Failed to load employees. Please try again.');
        }
    }

    // Show create form
    public function create()
    {
        return view('hr.employees.create');
    }

    // Add a new employee
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'employee_id' => 'required|string|unique:employees,employee_id',
                'phone' => 'nullable|string|max:20',
                'designation' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
                'joining_date' => 'nullable|date',
            ]);

            $employee = Employee::create($data);
            $employee->load('user');

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee created successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'user' => $employee->user ? [
                            'id' => $employee->user->id,
                            'name' => $employee->user->name,
                            'email' => $employee->user->email,
                        ] : null,
                    ]
                ], 201);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee created successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to create employee. Please try again.')
                ->withInput();
        }
    }

    // Show one employee
    public function show(Request $request, $id)
    {
        try {
            $employee = Employee::with('user')->findOrFail($id);

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee retrieved successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $employee->name ?? $employee->user->name ?? null,
                        'email' => $employee->email ?? $employee->user->email ?? null,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone ?? $employee->user->phone ?? null,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'user' => $employee->user ? [
                            'id' => $employee->user->id,
                            'name' => $employee->user->name,
                            'email' => $employee->user->email,
                            'phone' => $employee->user->phone,
                            'role' => $employee->user->role,
                        ] : null,
                    ]
                ], 200);
            }

            // Web request - return view
            return view('hr.employees.show', compact('employee'));
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('hr.employees.index')
                ->with('error', 'Employee not found.');
        }
    }

    // Show edit form
    public function edit($id)
    {
        $employee = Employee::with('user')->findOrFail($id);
        return view('hr.employees.edit', compact('employee'));
    }

    // Update an existing employee
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);

            $data = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|max:255',
                'employee_id' => 'sometimes|required|string|unique:employees,employee_id,' . $id,
                'phone' => 'nullable|string|max:20',
                'designation' => 'nullable|string|max:255',
                'region' => 'nullable|string|max:255',
                'joining_date' => 'nullable|date',
            ]);

            $employee->update($data);
            $employee->load('user');

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee updated successfully',
                    'data' => [
                        'id' => $employee->id,
                        'user_id' => $employee->user_id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'employee_id' => $employee->employee_id,
                        'phone' => $employee->phone,
                        'designation' => $employee->designation,
                        'region' => $employee->region,
                        'joining_date' => $employee->joining_date,
                        'created_at' => $employee->created_at,
                        'updated_at' => $employee->updated_at,
                        'user' => $employee->user ? [
                            'id' => $employee->user->id,
                            'name' => $employee->user->name,
                            'email' => $employee->user->email,
                        ] : null,
                    ]
                ], 200);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Failed to update employee. Please try again.')
                ->withInput();
        }
    }

    // Delete an employee
    public function destroy(Request $request, $id)
    {
        try {
            $employee = Employee::findOrFail($id);
            $employee->delete();

            // Check if this is an API request
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Employee deleted successfully'
                ], 200);
            }

            // Web request - redirect with success message
            return redirect()->route('hr.employees.index')
                ->with('success', 'Employee deleted successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete employee: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('hr.employees.index')
                ->with('error', 'Failed to delete employee. Please try again.');
        }
    }
}
