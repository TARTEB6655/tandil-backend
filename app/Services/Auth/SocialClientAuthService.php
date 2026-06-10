<?php

namespace App\Services\Auth;

use App\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class SocialClientAuthService
{
    public const PORTAL = 'client';

    /**
     * @param  array{sub: string, email: ?string, name: ?string, email_verified?: bool}  $claims
     */
    public function authenticateGoogle(array $claims, string $portal): JsonResponse
    {
        return $this->authenticate(
            provider: 'google',
            providerId: $claims['sub'],
            portal: $portal,
            email: $claims['email'] ?? null,
            name: $claims['name'] ?? null,
            emailVerified: (bool) ($claims['email_verified'] ?? false),
        );
    }

    /**
     * @param  array{sub: string, email: ?string, email_verified?: bool}  $claims
     */
    public function authenticateApple(
        array $claims,
        string $portal,
        ?string $nameOverride = null,
        ?string $emailOverride = null,
    ): JsonResponse {
        $email = filled($emailOverride) ? strtolower(trim($emailOverride)) : ($claims['email'] ?? null);
        $name = filled($nameOverride) ? trim($nameOverride) : null;

        return $this->authenticate(
            provider: 'apple',
            providerId: $claims['sub'],
            portal: $portal,
            email: $email,
            name: $name,
            emailVerified: (bool) ($claims['email_verified'] ?? false),
        );
    }

    private function authenticate(
        string $provider,
        string $providerId,
        string $portal,
        ?string $email,
        ?string $name,
        bool $emailVerified,
    ): JsonResponse {
        if ($portal !== self::PORTAL) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => [
                    'roles' => ['Only client social sign-in is supported.'],
                ],
            ], 422);
        }

        $idColumn = $provider === 'google' ? 'google_id' : 'apple_id';

        try {
            $user = DB::transaction(function () use ($idColumn, $providerId, $email, $name, $emailVerified) {
                $user = User::query()->where($idColumn, $providerId)->first();

                if (! $user && $email) {
                    $user = User::query()->where('email', $email)->first();
                    if ($user) {
                        $user->{$idColumn} = $providerId;
                    }
                }

                if ($user) {
                    return $this->updateExistingSocialUser($user, $idColumn, $providerId, $email, $name, $emailVerified);
                }

                return $this->createClientUser($idColumn, $providerId, $email, $name, $emailVerified);
            });
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 403);
        }

        if ($user->status !== 'active') {
            return ApiResponse::error('Account is not active. Please contact admin.', 403);
        }

        if (! $user->matchesLoginPortal($portal)) {
            return ApiResponse::error('Invalid login credentials.', 401);
        }

        $user->loadMissing(['roles:id,name']);

        $loginService = app(LoginService::class);
        $payload = $loginService->success($user, $portal);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $payload['token'],
                'role' => $payload['role'],
                'slug' => $payload['slug'],
                'user' => $payload['user'],
            ],
        ]);
    }

    private function updateExistingSocialUser(
        User $user,
        string $idColumn,
        string $providerId,
        ?string $email,
        ?string $name,
        bool $emailVerified,
    ): User {
        if (! $user->matchesLoginPortal(self::PORTAL) && ! $user->hasAppRole(self::PORTAL)) {
            throw new RuntimeException('This email is already registered with a different account type.');
        }

        $dirty = false;

        if (empty($user->{$idColumn})) {
            $user->{$idColumn} = $providerId;
            $dirty = true;
        }

        if ($email && empty($user->email)) {
            $exists = User::query()->where('email', $email)->where('id', '!=', $user->id)->exists();
            if ($exists) {
                throw new RuntimeException('This email is already registered with a different account.');
            }
            $user->email = $email;
            $dirty = true;
        }

        if ($name && (empty($user->name) || $user->name === 'Apple User' || $user->name === 'Google User')) {
            $user->name = $name;
            $dirty = true;
        }

        if ($emailVerified && ! $user->email_verified_at) {
            $user->email_verified_at = now();
            $dirty = true;
        }

        if ($dirty) {
            $user->save();
        }

        $this->ensureClientRole($user);

        return $user->fresh();
    }

    private function createClientUser(
        string $idColumn,
        string $providerId,
        ?string $email,
        ?string $name,
        bool $emailVerified,
    ): User {
        if ($email) {
            $existing = User::query()->where('email', $email)->first();
            if ($existing) {
                throw new RuntimeException('This email is already registered. Sign in with email and password, or use the same social account.');
            }
        }

        $displayName = $name ?: ($email ? Str::before($email, '@') : ($idColumn === 'google_id' ? 'Google User' : 'Apple User'));

        $user = User::create([
            $idColumn => $providerId,
            'name' => $displayName,
            'email' => $email,
            'password' => Str::password(32),
            'role' => self::PORTAL,
            'status' => 'active',
            'email_verified_at' => ($email && $emailVerified) ? now() : null,
        ]);

        $this->ensureClientRole($user);

        return $user;
    }

    private function ensureClientRole(User $user): void
    {
        if ($user->role !== self::PORTAL) {
            $user->role = self::PORTAL;
            $user->save();
        }

        try {
            if (class_exists(Role::class)) {
                Role::findOrCreate(self::PORTAL, 'web');
            }
            if (method_exists($user, 'assignRole') && ! $user->hasRole(self::PORTAL)) {
                $user->assignRole(self::PORTAL);
            }
        } catch (\Throwable $e) {
            // Spatie optional in some environments
        }
    }
}
