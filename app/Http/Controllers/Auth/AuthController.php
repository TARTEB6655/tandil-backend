<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Area;
use App\Models\TechnicianSignupRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
     * PUBLIC: zones/areas that accept technician signups (must have at least one supervisor).
     * GET /api/auth/technician-signup-areas — mobile app should call this to populate the picker instead of free-text / GPS strings.
     */
    public function technicianSignupAreas(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->technicianSignupAreasList(),
        ]);
    }

    /**
     * TECHNICIAN REGISTER
     * Dedicated endpoint for technician signup flow.
     */
    public function registerTechnician(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|max:20|unique:users,phone',
            'service_area' => 'nullable|string|max:255',
            'area_id' => 'nullable|integer|exists:areas,id',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $hasAreaId = $request->filled('area_id');
            $hasName = filled(trim((string) $request->input('service_area', '')));
            if (! $hasAreaId && ! $hasName) {
                $validator->errors()->add('service_area', 'Select a zone from the list (use area_id from GET /api/auth/technician-signup-areas) or send an exact service_area name.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $area = null;
        if (! empty($validated['area_id'])) {
            $area = Area::query()->find((int) $validated['area_id']);
        }
        if (! $area && filled(trim((string) ($validated['service_area'] ?? '')))) {
            $needle = strtolower(trim($validated['service_area']));
            $area = Area::query()->whereRaw('LOWER(TRIM(name)) = ?', [$needle])->first();
        }
        if (! $area) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid service_area or area_id. Pick a zone from available_areas (exact name or id from GET /api/auth/technician-signup-areas).',
                'errors' => [
                    'service_area' => ['No matching zone. GPS or free text will not match — use the list endpoint.'],
                ],
                'data' => [
                    'available_areas' => $this->technicianSignupAreasList(),
                ],
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

    /**
     * @return list<array{id: int, name: string, location: ?string, country: ?string}>
     */
    private function technicianSignupAreasList(): array
    {
        return Area::query()
            ->whereHas('supervisors')
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'country'])
            ->map(static fn (Area $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'location' => $a->location,
                'country' => $a->country ?? null,
            ])
            ->values()
            ->all();
    }
}
