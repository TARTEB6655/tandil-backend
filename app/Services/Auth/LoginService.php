<?php

namespace App\Services\Auth;

use App\Models\User;
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
        $user = User::query()
            ->where('email', $email)
            ->with(['roles:id,name'])
            ->first();

        if (! $user || ! Hash::check($password, $user->getAuthPassword())) {
            return $this->failure('Invalid login credentials.', 401);
        }

        if ($user->status !== 'active') {
            return $this->failure('Account is not active. Please contact admin.', 403);
        }

        if (! $user->matchesLoginPortal($portal)) {
            return $this->failure('Invalid login credentials.', 401);
        }

        return $this->success($user, $portal);
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

        // Drop stale portal tokens so personal_access_tokens does not grow without bound (slow logins).
        $user->tokens()->where('name', $tokenName)->delete();

        return $user->createToken($tokenName, [$portal])->plainTextToken;
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
