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
    }

    // Show list of employees
    public function index()
    {
        try {
            $employees = Employee::with('user')->get();
            return response()->json(['status' => true, 'data' => $employees]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employees: ' . $e->getMessage()
            ], 500);
        }
    }

    // Add a new employee
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'employee_id' => 'required|string|unique:employees,employee_id',
                'phone' => 'nullable|string',
                'designation' => 'nullable|string',
                'region' => 'nullable|string',
                'joining_date' => 'nullable|date',
            ]);

            $employee = Employee::create($data);

            return response()->json(['status' => true, 'data' => $employee], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create employee: ' . $e->getMessage()
            ], 500);
        }
    }

    // Show one employee
    public function show($id)
    {
        try {
            $employee = Employee::with('user')->find($id);

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found'], 404);
            }

            return response()->json(['status' => true, 'data' => $employee]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch employee: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update an existing employee
    public function update(Request $request, $id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found'], 404);
            }

            $data = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'employee_id' => 'sometimes|required|string|unique:employees,employee_id,' . $id,
                'phone' => 'nullable|string',
                'designation' => 'nullable|string',
                'region' => 'nullable|string',
                'joining_date' => 'nullable|date',
            ]);

            $employee->update($data);

            return response()->json(['status' => true, 'data' => $employee]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update employee: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete an employee
    public function destroy($id)
    {
        try {
            $employee = Employee::find($id);

            if (!$employee) {
                return response()->json(['status' => false, 'message' => 'Employee not found'], 404);
            }

            $employee->delete();

            return response()->json(['status' => true, 'message' => 'Employee deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete employee: ' . $e->getMessage()
            ], 500);
        }
    }
}
