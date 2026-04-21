<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Area;
use App\Models\TechnicianSignupRequest;
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
        // Note: User model has 'password' => 'hashed' cast, so password is auto-hashed
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'phone'    => $validated['phone'] ?? null,
            'password' => $validated['password'], // Auto-hashed by model cast
            'role'     => $validated['role'],
            'status'   => 'active',
        ]);

        // Assign Spatie role
        $user->assignRole($validated['role']);

        // Generate token
        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully.',
            'data'    => [
                'token' => $token,
                'role'  => $user->role,
                'user'  => $user
            ]
        ], 201);
    }

    /**
     * TECHNICIAN REGISTER
     * Dedicated endpoint for technician signup flow.
     */
    public function registerTechnician(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'service_area' => 'required|string|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $area = Area::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['service_area']))])->first();
        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid service_area. Please choose an existing area.',
            ], 422);
        }

        if (! $area->supervisors()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No supervisor is assigned to this area yet. Please contact admin.',
            ], 422);
        }

        $pendingExists = TechnicianSignupRequest::query()
            ->where('status', 'pending')
            ->where(function ($q) use ($validated) {
                $q->where('email', $validated['email'])
                    ->orWhere('phone', $validated['phone']);
            })
            ->exists();
        if ($pendingExists) {
            return response()->json([
                'success' => false,
                'message' => 'A signup request with this email or phone is already pending approval.',
            ], 422);
        }

        $signupRequest = TechnicianSignupRequest::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'area_id' => $area->id,
            'service_area' => $area->name,
            'password' => Hash::make($validated['password']),
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Technician signup request submitted. Waiting for supervisor approval.',
            'data' => [
                'request_id' => $signupRequest->id,
                'status' => $signupRequest->status,
                'service_area' => $signupRequest->service_area,
            ],
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
            'success' => true,
            'message' => 'Login successful.',
            'data'    => [
                'token' => $token,
                'role'  => $user->role,
                'user'  => $user
            ]
        ]);
    }

    /**
     * PROFILE
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully.',
            'data'    => [
                'role' => $user->role,
                'user' => $user
            ]
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
