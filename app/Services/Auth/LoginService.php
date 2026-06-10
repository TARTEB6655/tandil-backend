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
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->first();

        if (! $user || ! $this->passwordMatches($user, $password)) {
            return $this->failure('Invalid login credentials.', 401);
        }

        if ($user->status !== 'active') {
            return $this->failure('Account is not active. Please contact admin.', 403);
        }

        if (! $user->matchesLoginPortal($portal)) {
            return $this->failure('Invalid login credentials.', 401);
        }

        try {
            return $this->success($user, $portal);
        } catch (\Throwable $e) {
            report($e);

            return $this->failure('Could not complete login. Please try again.', 503);
        }
    }

    private function passwordMatches(User $user, string $password): bool
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
