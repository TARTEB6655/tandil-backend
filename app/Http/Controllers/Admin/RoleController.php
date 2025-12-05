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
        // Check if this is an API request
        if (request()->expectsJson() || request()->is('api/*')) {
            $roles = Role::with('permissions')->get();
            return response()->json([
                'status' => true,
                'data' => $roles
            ], 200);
        }
        
        $roles = Role::withCount('users')->with('permissions')->get();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show role details with permissions
     */
    public function show($id)
    {
        $role = Role::with(['users', 'permissions'])->findOrFail($id);
        $allPermissions = \Spatie\Permission\Models\Permission::all()->groupBy('guard_name');
        return view('admin.roles.show', compact('role', 'allPermissions'));
    }

    /**
     * Show form to create a new role
     */
    public function create()
    {
        $permissions = \Spatie\Permission\Models\Permission::all()->groupBy('guard_name');
        return view('admin.roles.create', compact('permissions'));
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create(['name' => $validated['name']]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'data' => $role->load('permissions')
            ], 201);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully');
    }

    /**
     * Show form to edit role
     */
    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        $permissions = \Spatie\Permission\Models\Permission::all()->groupBy('guard_name');
        return view('admin.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update(['name' => $validated['name']]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        } else {
            $role->syncPermissions([]);
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role updated successfully');
    }

    /**
     * Delete role
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully');
    }
}
