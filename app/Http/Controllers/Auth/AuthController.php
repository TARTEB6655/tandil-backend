<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * REGISTER
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'phone'    => 'nullable|string|max:20|unique:users,phone',
            'password' => 'required|string|min:6|confirmed',
            'role'     => 'required|in:client,technician,supervisor,area_manager,hr,admin',
        ]);

        // Create user
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
            'status'   => 'active',
        ]);

        // Assign Spatie role
        $user->assignRole($validated['role']);

        // Generate token
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'User registered successfully.',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user,
            'data'    => $user
        ], 201);
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return ApiResponse::error('Invalid login credentials.', 401);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('Account is not active. Please contact admin.', 403);
        }

        // Create new token
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'status'  => true,
            'message' => 'Login successful.',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user,
            'data'    => $user
        ]);
    }

    /**
     * PROFILE
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'status' => true,
            'message' => 'User retrieved successfully.',
            'role'   => $user->role,
            'user'   => $user,
            'data'   => $user
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success('Logged out successfully.');
    }

    /**
     * FORGOT PASSWORD
     */
    public function forgotPassword(Request $request)
    {
        // TODO: Implement password reset functionality
        return ApiResponse::error('Password reset feature not implemented yet', 501);
    }

    /**
     * VERIFY OTP
     */
    public function verifyOtp(Request $request)
    {
        // TODO: Implement OTP verification
        return ApiResponse::error('OTP verification not implemented yet', 501);
    }

    /**
     * RESET PASSWORD
     */
    public function resetPassword(Request $request)
    {
        // TODO: Implement password reset
        return ApiResponse::error('Password reset feature not implemented yet', 501);
    }
}
