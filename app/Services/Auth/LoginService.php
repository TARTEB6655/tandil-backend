<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\VendorLoginGate;
use Illuminate\Support\Facades\Hash;

class LoginService
{
    /**
     * @return array{
     *   ok: bool,
     *   status?: int,
     *   error?: string,
     *   token?: string,
     *   role?: ?string,
     *   slug?: string,
     *   user?: array<string, mixed>
     * }
     */
    public function attemptPasswordLogin(string $email, string $password, string $portal): array
    {
        $email = strtolower(trim($email));
        $candidates = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orderBy('id')
            ->get();

        $user = null;
        foreach ($candidates as $candidate) {
            if (! $this->passwordMatches($candidate, $password)) {
                continue;
            }
            if (! $candidate->matchesLoginPortal($portal)) {
                continue;
            }
            $user = $candidate;
            break;
        }

        if (! $user) {
            return $this->failure('Invalid login credentials.', 401);
        }

        if ($user->status !== 'active') {
            return $this->failure('Account is not active. Please contact admin.', 403);
        }

        if ($portal === 'vendor') {
            $blocked = VendorLoginGate::blockedMessageForUser($user);
            if ($blocked !== null) {
                return $this->failure($blocked, 403);
            }
        }

        try {
            return $this->success($user, $portal);
        } catch (\Throwable $e) {
            report($e);

            return $this->failure('Could not complete login. Please try again.', 503);
        }
    }

    public function passwordMatches(User $user, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $hash = $user->getRawOriginal('password');
        if (is_string($hash) && $hash !== '' && Hash::check($password, $hash)) {
            return true;
        }

        try {
            return Hash::check($password, (string) $user->password);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *   ok: true,
     *   token: string,
     *   role: ?string,
     *   slug: string,
     *   user: array<string, mixed>
     * }
     */
    public function success(User $user, string $portal): array
    {
        $token = $this->issuePortalToken($user, $portal);

        return [
            'ok' => true,
            'token' => $token,
            'role' => $user->role,
            'slug' => $portal,
            'user' => $user->toLoginArray(),
        ];
    }

    public function issuePortalToken(User $user, string $portal): string
    {
        $tokenName = 'api_'.$portal;

        // Never revoke existing portal tokens on login — same behaviour as long-lived admin sessions.
        // Mobile/Postman keep using the saved token until explicit logout.
        return $user->createToken($tokenName, [$portal])->plainTextToken;
    }

    /**
     * Vendor portal context returned on login so mobile can route pending vs approved vendors.
     *
     * @return array<string, mixed>|null
     */
    public function vendorPortalPayloadForUser(?User $user): ?array
    {
        if ($user === null || ! $user->hasAppRole('vendor')) {
            return null;
        }

        $vendor = $user->vendor()->with('profile')->first();
        if ($vendor === null) {
            return null;
        }

        return [
            'vendor_id' => $vendor->id,
            'status' => $vendor->status,
            'is_approved' => $vendor->isApproved(),
            'business_name' => $vendor->profile?->business_name,
            'rejection_reason' => $vendor->rejection_reason,
            'onboarding_completed_at' => $vendor->profile?->onboarding_completed_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: false, error: string, status: int}
     */
    private function failure(string $error, int $status): array
    {
        return [
            'ok' => false,
            'error' => $error,
            'status' => $status,
        ];
    }
}
