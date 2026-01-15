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
        $query = User::with('roles');

        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%");
        }

        // Filter by role
        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        // Return JSON for API requests
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully.',
                'data' => $users->items(),
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
}
