<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        // Only admin users can manage roles
        $this->middleware('role:admin');
    }

    /**
     * List all roles
     */
    public function index()
    {
        $roles = Role::all();

        return response()->json([
            'status' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
        ]);

        $role = Role::create(['name' => $validated['name']]);

        return response()->json([
            'status' => true,
            'data' => $role,
        ], 201);
    }
}
