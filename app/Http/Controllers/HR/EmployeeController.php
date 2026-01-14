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
        $this->middleware(['auth:sanctum', 'role:hr|admin']);
        
        // Force JSON responses for API requests
        $this->middleware(function ($request, $next) {
            $request->headers->set('Accept', 'application/json');
            return $next($request);
        });
    }

    // Show list of employees
    public function index(Request $request)
    {
        try {
            $employees = Employee::with('user')
                ->orderBy('created_at', 'desc')
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
                'status' => true,
                'message' => 'Employees retrieved successfully',
                'data' => $employees,
                'total' => $employees->count()
            ], 200, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employees: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
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

            return response()->json([
                'status' => true,
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
            ], 201, ['Content-Type' => 'application/json']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
    }

    // Show one employee
    public function show(Request $request, $id)
    {
        try {
            $employee = Employee::with('user')->find($id);

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404, ['Content-Type' => 'application/json']);
            }

            return response()->json([
                'status' => true,
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
            ], 200, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employee: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
    }

    // Update an existing employee
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404, ['Content-Type' => 'application/json']);
            }

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

            return response()->json([
                'status' => true,
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
            ], 200, ['Content-Type' => 'application/json']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
    }

    // Delete an employee
    public function destroy(Request $request, $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found'
                ], 404, ['Content-Type' => 'application/json']);
            }

            $employee->delete();

            return response()->json([
                'status' => true,
                'message' => 'Employee deleted successfully'
            ], 200, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ], 500, ['Content-Type' => 'application/json']);
        }
    }
}
