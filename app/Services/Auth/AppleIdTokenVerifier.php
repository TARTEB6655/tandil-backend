<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;
use Throwable;

class AppleIdTokenVerifier
{
    private const ISSUER = 'https://appleid.apple.com';

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    public function __construct()
    {
        // Apple / device clock skew
        JWT::$leeway = 60;
    }

    /**
     * @return array{sub: string, email: ?string, email_verified: bool}
     */
    public function verify(string $idToken): array
    {
        $idToken = trim($idToken);
        if ($idToken === '') {
            throw new RuntimeException('Invalid Apple ID token.');
        }

        $allowedAudiences = $this->allowedClientIds();
        $headers = null;

        try {
            $decoded = $this->decodeToken($idToken, $headers);
        } catch (Throwable $e) {
            Log::warning('Apple ID token verification failed', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            throw new RuntimeException('Invalid Apple ID token.', 0, $e);
        }

        $payload = (array) $decoded;

        if (($payload['iss'] ?? '') !== self::ISSUER) {
            throw new RuntimeException('Invalid Apple ID token issuer.');
        }

        if ($allowedAudiences !== [] && ! $this->audienceMatches($payload['aud'] ?? null, $allowedAudiences)) {
            Log::warning('Apple ID token audience rejected', [
                'aud' => $payload['aud'] ?? null,
                'allowed' => $allowedAudiences,
            ]);
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

    private function decodeToken(string $idToken, ?stdClass &$headers): stdClass
    {
        try {
            return JWT::decode($idToken, JWK::parseKeySet($this->applePublicKeys()), $headers);
        } catch (Throwable $jwksError) {
            return $this->decodeWithX5cCertificate($idToken, $jwksError, $headers);
        }
    }

    private function decodeWithX5cCertificate(string $idToken, Throwable $jwksError, ?stdClass &$headers): stdClass
    {
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw $jwksError;
        }

        $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
        if (! is_object($header) || empty($header->x5c) || ! is_array($header->x5c) || empty($header->x5c[0])) {
            throw $jwksError;
        }

        $alg = is_string($header->alg ?? null) && $header->alg !== '' ? $header->alg : 'ES256';
        $pem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split((string) $header->x5c[0], 64, "\n")
            ."-----END CERTIFICATE-----\n";

        $publicKey = openssl_pkey_get_public($pem);
        if ($publicKey === false) {
            throw $jwksError;
        }

        return JWT::decode($idToken, new Key($publicKey, $alg), $headers);
    }

    /**
     * Apple may send aud as a string or an array of client IDs.
     *
     * @param  mixed  $aud
     * @param  list<string>  $allowedAudiences
     */
    private function audienceMatches(mixed $aud, array $allowedAudiences): bool
    {
        $candidates = [];
        if (is_string($aud) && $aud !== '') {
            $candidates[] = $aud;
        } elseif (is_array($aud)) {
            foreach ($aud as $value) {
                if (is_string($value) && $value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        if ($candidates === []) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $allowedAudiences, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function applePublicKeys(): array
    {
        return Cache::remember('apple_sign_in_jwks', 3600, function () {
            $response = Http::timeout(15)->acceptJson()->get(self::KEYS_URL);
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
