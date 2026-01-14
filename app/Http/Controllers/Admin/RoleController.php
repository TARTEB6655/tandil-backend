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
        
        // Get roles with accurate user counts from both Spatie Permission and role field
        $roles = Role::with('permissions')->orderBy('name')->get()->map(function($role) {
            // Count users from Spatie Permission pivot table
            $spatieCount = \App\Models\User::whereHas('roles', function($q) use ($role) {
                $q->where('roles.id', $role->id);
            })->where('status', 'active')->count();
            
            // Count users from role field that might not be in Spatie pivot
            $roleFieldCount = \App\Models\User::where('role', $role->name)
                ->where('status', 'active')
                ->whereDoesntHave('roles', function($q) use ($role) {
                    $q->where('roles.id', $role->id);
                })->count();
            
            // Total count
            $totalCount = $spatieCount + $roleFieldCount;
            
            // Create a role object with the accurate count
            $roleObj = clone $role;
            $roleObj->users_count = $totalCount;
            
            return $roleObj;
        });
        
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show role details with permissions
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        
        // Get users from both Spatie Permission and role field
        $spatieUsers = \App\Models\User::whereHas('roles', function($q) use ($role) {
            $q->where('roles.id', $role->id);
        })->where('status', 'active')->orderBy('name')->get();
        
        $roleFieldUsers = \App\Models\User::where('role', $role->name)
            ->where('status', 'active')
            ->whereDoesntHave('roles', function($q) use ($role) {
                $q->where('roles.id', $role->id);
            })->orderBy('name')->get();
        
        // Merge users and remove duplicates
        $allUsers = $spatieUsers->merge($roleFieldUsers)->unique('id');
        $role->users = $allUsers;
        
        $allPermissions = \Spatie\Permission\Models\Permission::all()->groupBy('guard_name');
        return view('admin.roles.show', compact('role', 'allPermissions'));
    }

    /**
     * Show form to create a new role
     */
    public function create()
    {
        $permissions = \Spatie\Permission\Models\Permission::all()->groupBy('guard_name');
        
        // Get roles with accurate user counts
        $existingRoles = Role::with('permissions')->orderBy('name')->get()->map(function($role) {
            $spatieCount = \App\Models\User::whereHas('roles', function($q) use ($role) {
                $q->where('roles.id', $role->id);
            })->where('status', 'active')->count();
            
            $roleFieldCount = \App\Models\User::where('role', $role->name)
                ->where('status', 'active')
                ->whereDoesntHave('roles', function($q) use ($role) {
                    $q->where('roles.id', $role->id);
                })->count();
            
            $roleObj = clone $role;
            $roleObj->users_count = $spatieCount + $roleFieldCount;
            return $roleObj;
        });
        
        return view('admin.roles.create', compact('permissions', 'existingRoles'));
    }

    /**
     * Create a new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

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
        // Get all existing roles from database for dropdown
        $existingRoles = Role::orderBy('name')->get();
        return view('admin.roles.edit', compact('role', 'permissions', 'existingRoles'));
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

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
