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

        return view('admin.users.index', compact('users'));
    }

    // Show user details by ID
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);
        return view('admin.users.show', compact('user'));
    }

    // Show form for creating new user
    public function create()
    {
        $roles = \Spatie\Permission\Models\Role::all();
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
            'password' => bcrypt($data['password']),
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    // Show form for editing user
    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = \Spatie\Permission\Models\Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    // Update user
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
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
        if (isset($data['password'])) $user->password = bcrypt($data['password']);

        // Update role
        if (isset($data['role'])) {
            $user->role = $data['role'];       // update column
            $user->syncRoles([$data['role']]); // update spatie role
        }

        $user->save();

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'User updated successfully.');
    }

    // Delete user
    public function destroy($id, Request $request)
    {
        $admin = $request->user();

        if ((int)$admin->id === (int)$id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account');
        }

        $user = User::find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')
                ->with('error', 'User not found');
        }

        $user->delete();

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
        $user->password = bcrypt($request->password);
        $user->save();

        return redirect()->back()->with('success', 'Password reset successfully');
    }

    // Ban/Deactivate user
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->status = $user->status === 'active' ? 'inactive' : 'active';
        $user->save();

        $status = $user->status === 'active' ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "User {$status} successfully");
    }
}
