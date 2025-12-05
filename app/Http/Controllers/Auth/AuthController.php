<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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
        try {
            $validated = $request->validate([
                'name'     => 'required|string|max:100',
                'email'    => 'required|email|unique:users,email',
                'phone'    => 'nullable|string|max:20',
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
                'user'    => $user
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * LOGIN
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (! $user || ! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid login credentials.'
                ], 401);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'status'  => false,
                    'message' => 'Account is not active. Please contact admin.'
                ], 403);
            }

            // Create new token
            $token = $user->createToken('api_token')->plainTextToken;

            return response()->json([
                'status'  => true,
                'message' => 'Login successful.',
                'token'   => $token,
                'user'    => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'Login failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * PROFILE
     */
    public function profile(Request $request)
    {
        return response()->json([
            'status' => true,
            'user'   => $request->user()
        ]);
    }

    /**
     * LOGOUT
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully.'
        ]);
    }
}
