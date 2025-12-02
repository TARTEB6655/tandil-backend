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
    public function index()
    {
        $users = User::select(['id', 'name', 'email', 'phone', 'status', 'created_at'])
            ->with('roles:id,name')
            ->get();

        return response()->json(['status' => true, 'data' => $users], 200);
    }

    // Show user details by ID
    public function show($id)
    {
        $user = User::with('roles:id,name')->find($id);

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        return response()->json(['status' => true, 'data' => $user], 200);
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
            'role' => $data['role'],      // update role column
            'status' => $data['status'],
        ]);

        $user->assignRole($data['role']);

        return response()->json(['status' => true, 'data' => $user], 201);
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

        return response()->json(['status' => true, 'data' => $user], 200);
    }

    // Delete user
    public function destroy($id, Request $request)
    {
        $admin = $request->user();

        if ((int)$admin->id === (int)$id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['status' => true, 'message' => 'User deleted successfully'], 200);
    }
}
