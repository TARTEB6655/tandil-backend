<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleIdTokenVerifier
{
    /**
     * @return array{sub: string, email: ?string, name: ?string, email_verified: bool}
     */
    public function verify(string $idToken): array
    {
        $response = Http::connectTimeout(2)
            ->timeout(4)
            ->acceptJson()
            ->get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Invalid Google ID token.');
        }

        $payload = $response->json();
        if (! is_array($payload) || empty($payload['sub'])) {
            throw new RuntimeException('Invalid Google ID token.');
        }

        $allowedAudiences = $this->allowedClientIds();
        if ($allowedAudiences !== [] && ! in_array((string) ($payload['aud'] ?? ''), $allowedAudiences, true)) {
            throw new RuntimeException('Google ID token audience is not allowed.');
        }

        return [
            'sub' => (string) $payload['sub'],
            'email' => isset($payload['email']) ? strtolower(trim((string) $payload['email'])) : null,
            'name' => isset($payload['name']) ? trim((string) $payload['name']) : null,
            'email_verified' => filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedClientIds(): array
    {
        $raw = (string) config('services.google.client_ids', '');
        if ($raw === '') {
            $single = trim((string) config('services.google.client_id', ''));

            return $single !== '' ? [$single] : [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
