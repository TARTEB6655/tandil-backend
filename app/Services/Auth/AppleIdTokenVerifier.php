<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class AppleIdTokenVerifier
{
    private const ISSUER = 'https://appleid.apple.com';

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    /**
     * @return array{sub: string, email: ?string, email_verified: bool}
     */
    public function verify(string $idToken): array
    {
        $keys = $this->applePublicKeys();
        $allowedAudiences = $this->allowedClientIds();

        try {
            $decoded = JWT::decode($idToken, JWK::parseKeySet($keys));
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid Apple ID token.', 0, $e);
        }

        $payload = (array) $decoded;

        if (($payload['iss'] ?? '') !== self::ISSUER) {
            throw new RuntimeException('Invalid Apple ID token issuer.');
        }

        if ($allowedAudiences !== [] && ! in_array((string) ($payload['aud'] ?? ''), $allowedAudiences, true)) {
            throw new RuntimeException('Apple ID token audience is not allowed.');
        }

        if (empty($payload['sub'])) {
            throw new RuntimeException('Invalid Apple ID token.');
        }

        $email = isset($payload['email']) ? strtolower(trim((string) $payload['email'])) : null;

        return [
            'sub' => (string) $payload['sub'],
            'email' => $email !== '' ? $email : null,
            'email_verified' => filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function applePublicKeys(): array
    {
        return Cache::remember('apple_sign_in_jwks', 3600, function () {
            $response = Http::timeout(10)->acceptJson()->get(self::KEYS_URL);
            if (! $response->successful()) {
                throw new RuntimeException('Unable to fetch Apple public keys.');
            }

            $keys = $response->json('keys');
            if (! is_array($keys) || $keys === []) {
                throw new RuntimeException('Unable to fetch Apple public keys.');
            }

            return ['keys' => $keys];
        });
    }

    /**
     * @return list<string>
     */
    private function allowedClientIds(): array
    {
        $raw = (string) config('services.apple.client_ids', '');
        if ($raw === '') {
            $single = trim((string) config('services.apple.client_id', ''));

            return $single !== '' ? [$single] : [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
