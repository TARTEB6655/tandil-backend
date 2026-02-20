<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckoutComService
{
    protected string $secretKey;
    protected string $publicKey;
    protected bool $sandbox;

    public function __construct()
    {
        $this->secretKey = config('checkout.secret_key', '');
        $this->publicKey = config('checkout.public_key', '');
        $this->sandbox = config('checkout.sandbox', true);
    }

    protected function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://api.sandbox.checkout.com'
            : 'https://api.checkout.com';
    }

    /**
     * Create a payment session. Returns session id for frontend to open payment UI.
     * Frontend must never create payment directly; backend creates session only.
     */
    public function createPaymentSession(
        string $orderReference,
        float $amount,
        string $currency,
        string $successUrl,
        string $failureUrl,
        array $metadata = []
    ): array {
        if (empty($this->secretKey)) {
            return ['error' => 'Checkout.com secret key not configured', 'session_id' => null];
        }

        $payload = [
            'amount' => (int) round($amount * 100),
            'currency' => strtoupper($currency),
            'reference' => $orderReference,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'metadata' => array_merge(['order_id' => $orderReference], $metadata),
        ];

        $url = $this->baseUrl() . '/payment-sessions';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->post($url, $payload);

        $data = $response->json();

        if ($response->successful() && ! empty($data['id'])) {
            return [
                'session_id' => $data['id'],
                'public_key' => $this->publicKey,
                'client_session_id' => $data['client_session_id'] ?? null,
            ];
        }

        Log::warning('Checkout.com createPaymentSession failed', [
            'status' => $response->status(),
            'body' => $data,
        ]);

        return [
            'error' => $data['error_message'] ?? $data['message'] ?? $response->body(),
            'session_id' => null,
        ];
    }

    /**
     * Verify webhook signature (Cko-Signature header = HMAC of raw body).
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $secret = config('checkout.webhook_secret', '');
        if (empty($secret)) {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    /**
     * Get public key for frontend (to initialize SDK). Never expose secret key.
     */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }
}
