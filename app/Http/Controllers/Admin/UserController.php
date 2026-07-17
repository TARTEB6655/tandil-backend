<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        // Only admins can access these routes
        $this->middleware('role:admin');
    }

    // List users with their roles
    public function index(Request $request)
    {
        $query = User::with(['roles', 'supervisedAreas:id,name', 'assignedAreas:id,name']);
        if (! $this->isVendorAudienceRequest($request)) {
            $this->withoutVendors($query);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        }

        // Filter by role (use same logic as statistics so list and count match)
        if ($request->has('role') && $request->role) {
            $role = $request->role;
            $query->where(function ($q) use ($role) {
                $q->where('role', $role)
                    ->orWhereHas('roles', fn ($r) => $r->where('name', $role));
            });
        }

        // Filter by category (for mobile app – same as role so list and statistics match)
        if ($request->has('category') && $request->category && $request->category !== 'all') {
            switch ($request->category) {
                case 'workers':
                    $query->where(function ($q) {
                        $q->where('role', 'technician')->orWhereHas('roles', fn ($r) => $r->where('name', 'technician'));
                    });
                    break;
                case 'supervisors':
                case 'supervisor': // allow singular so tab click always returns supervisor list
                    $query->where(function ($q) {
                        $q->where('role', 'supervisor')->orWhereHas('roles', fn ($r) => $r->where('name', 'supervisor'));
                    });
                    break;
                case 'managers':
                    $query->where(function ($q) {
                        $q->where('role', 'area_manager')->orWhereHas('roles', fn ($r) => $r->where('name', 'area_manager'));
                    });
                    break;
                case 'clients':
                    $query->where(function ($q) {
                        $q->where('role', 'client')->orWhereHas('roles', fn ($r) => $r->where('name', 'client'));
                    });
                    break;
                case 'vendors':
                case 'vendor':
                    $query->where(function ($q) {
                        $q->where('role', 'vendor')->orWhereHas('roles', fn ($r) => $r->where('name', 'vendor'));
                    });
                    break;
                default:
                    break;
            }
        }

        // Filter by status (treat null as active so list matches statistics when app sends status=active)
        if ($request->has('status') && $request->status) {
            if (strtolower($request->status) === 'active') {
                $query->where(function ($q) {
                    $q->where('status', 'active')->orWhereNull('status');
                });
            } else {
                $query->where('status', $request->status);
            }
        }

        $perPage = (int) $request->query('per_page', 15);
        $perPage = $perPage > 0 ? min($perPage, 100) : 15;
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            $formattedUsers = $users->map(function($user) {
                // Get role from Spatie Permission or fallback to role field
                $spatieRole = $user->roles->first();
                $roleName = $spatieRole ? $spatieRole->name : ($user->role ?? 'No Role');
                
                // Format user ID based on role
                $userIdPrefix = $this->getUserIdPrefix($roleName);
                $formattedId = $userIdPrefix . '-' . str_pad($user->id, 4, '0', STR_PAD_LEFT);
                
                // Get role display name
                $roleDisplay = $this->getRoleDisplayName($roleName);
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $roleName,
                    'role_display' => $roleDisplay,
                    'employee_id' => $formattedId,
                    'status' => $user->status,
                    'avatar' => strtoupper(substr($user->name, 0, 1)),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });
            
            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully.',
                'data' => [
                    'data' => $formattedUsers,
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ]
            ]);
        }

        return view('admin.users.index', compact('users'));
    }

    /**
     * Get user statistics for mobile app
     * GET /api/admin/users/statistics
     * Counts by role column or Spatie role so list and stats always match.
     */
    public function statistics(Request $request)
    {
        $allUsers = $this->withoutVendors(User::query())->count();
        $workers = $this->usersByRoleQuery('technician')->count();
        $supervisors = $this->usersByRoleQuery('supervisor')->count();
        $managers = $this->usersByRoleQuery('area_manager')->count();
        $clients = $this->usersByRoleQuery('client')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'all_users' => $allUsers,
                'workers' => $workers,
                'supervisors' => $supervisors,
                'managers' => $managers,
                'clients' => $clients,
            ]
        ]);
    }

    /**
     * Vendors are managed under Marketplace → Vendor Management, not User Management.
     */
    private function isVendorAudienceRequest(Request $request): bool
    {
        $role = strtolower(trim((string) $request->query('role', '')));
        $category = strtolower(trim((string) $request->query('category', '')));

        return $role === 'vendor' || in_array($category, ['vendor', 'vendors'], true);
    }

    /**
     * Vendors are managed under Marketplace → Vendor Management, not User Management.
     */
    private function withoutVendors($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->whereNull('role')->orWhere('role', '!=', 'vendor');
            })->whereDoesntHave('roles', fn ($r) => $r->where('name', 'vendor'));
        });
    }

    /** Users that have this role in users.role column OR via Spatie (so list and statistics match). */
    private function usersByRoleQuery(string $role)
    {
        return User::where(function ($q) use ($role) {
            $q->where('role', $role)
                ->orWhereHas('roles', fn ($r) => $r->where('name', $role));
        });
    }

    // Show user details by ID
    public function show(Request $request, $id)
    {
        $user = User::with('roles')->findOrFail($id);
        
        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully.',
                'data' => $user
            ]);
        }
        
        return view('admin.users.show', compact('user'));
    }

    // Show form for creating new user
    public function create()
    {
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();
        return view('admin.users.create', compact('roles'));
    }

    // Create new user
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::exists('roles', 'name')],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'], // Model will auto-hash due to 'hashed' cast
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        // Ensure role exists before assigning
        $role = \Spatie\Permission\Models\Role::where('name', $data['role'])->first();
        if ($role) {
            $user->assignRole($data['role']);
        } else {
            // Create role if it doesn't exist
            $role = \Spatie\Permission\Models\Role::create(['name' => $data['role'], 'guard_name' => 'web']);
            $user->assignRole($role);
        }

        // 🔔 Notify the new user (if active)
        try {
            if ($user->status === 'active') {
                $user->notify(new \App\Notifications\AdminNotification(
                    'Account Created',
                    "Your account has been created. You can now login with your credentials. Role: " . ucfirst($data['role'])
                ));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send user creation notification: ' . $e->getMessage());
        }

        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'User created successfully.',
                'data' => $user->load('roles')
            ], 201);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    // Show form for editing user
    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = \Spatie\Permission\Models\Role::orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    // Update user
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            $isApi = $request->expectsJson() || $request->is('api/*');
            if ($isApi) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }
            return redirect()->route('admin.users.index')
                ->with('error', 'User not found');
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['sometimes', 'required', Rule::exists('roles', 'name')],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        // Update basic fields
        if (isset($data['name'])) $user->name = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];
        if (array_key_exists('phone', $data)) $user->phone = $data['phone'] ?? null;
        if (isset($data['status'])) $user->status = $data['status'];
        if (isset($data['password'])) $user->password = $data['password']; // Model will auto-hash due to 'hashed' cast

        // Update role
        if (isset($data['role'])) {
            $user->role = $data['role'];       // update column
            
            // Ensure role exists before syncing
            $role = \Spatie\Permission\Models\Role::where('name', $data['role'])->first();
            if ($role) {
                $user->syncRoles([$data['role']]); // update spatie role
            } else {
                // Create role if it doesn't exist
                $role = \Spatie\Permission\Models\Role::create(['name' => $data['role'], 'guard_name' => 'web']);
                $user->syncRoles([$role]);
            }
        }

        $user->save();

        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully.',
                'data' => $user->load('roles')
            ]);
        }

        // Redirect based on request
        if ($request->has('redirect_to') && $request->redirect_to === 'dashboard') {
            return redirect()->route('admin.dashboard')
                ->with('success', 'User role updated successfully.');
        }

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    // Delete user
    public function destroy($id, Request $request)
    {
        $admin = $request->user();
        $isApi = $request->expectsJson() || $request->is('api/*');

        if ((int)$admin->id === (int)$id) {
            if ($isApi) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account');
        }

        $user = User::find($id);

        if (!$user) {
            if ($isApi) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }
            return redirect()->route('admin.users.index')
                ->with('error', 'User not found');
        }

        $user->delete();

        if ($isApi) {
            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully.'
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    // Reset password
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::findOrFail($id);
        // Model will auto-hash due to 'hashed' cast
        $user->password = $request->password;
        $user->save();

        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully.'
            ]);
        }

        return redirect()->back()->with('success', 'Password reset successfully');
    }

    // Ban/Deactivate user
    public function toggleStatus(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $status = $user->status === 'active' ? 'activated' : 'deactivated';
        
        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => "User {$status} successfully.",
                'data' => $user
            ]);
        }

        return redirect()->back()->with('success', "User {$status} successfully");
    }

    /**
     * Get user ID prefix based on role
     */
    private function getUserIdPrefix($role)
    {
        $prefixes = [
            'client' => 'CLT',
            'technician' => 'EMP',
            'supervisor' => 'SUP',
            'area_manager' => 'AM',
            'hr' => 'HR',
            'vendor' => 'VND',
            'admin' => 'ADM',
        ];
        
        return $prefixes[strtolower($role)] ?? 'USR';
    }

    /**
     * Get role display name
     */
    private function getRoleDisplayName($role)
    {
        $displayNames = [
            'client' => 'Client',
            'technician' => 'Field Worker',
            'supervisor' => 'Supervisor',
            'area_manager' => 'Area Manager',
            'hr' => 'HR Manager',
            'vendor' => 'Vendor',
            'admin' => 'Administrator',
        ];
        
        return $displayNames[strtolower($role)] ?? ucfirst($role);
    }
}
