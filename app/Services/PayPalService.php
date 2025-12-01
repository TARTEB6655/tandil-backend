<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class PayPalService
{
    protected string $clientId;
    protected string $secret;
    protected bool $sandbox;
    protected $sdkClient = null;

    public function __construct()
    {
        $cfg = config('payments.paypal', []);
        $this->clientId = $cfg['client_id'] ?? '';
        $this->secret = $cfg['secret'] ?? '';
        $this->sandbox = $cfg['sandbox'] ?? true;

        // initialize SDK client when available
        if (class_exists('\\PayPalCheckoutSdk\\Core\\PayPalHttpClient') && class_exists('\\PayPalCheckoutSdk\\Core\\SandboxEnvironment')) {
            try {
                $envClass = $this->sandbox ? '\\PayPalCheckoutSdk\\Core\\SandboxEnvironment' : '\\PayPalCheckoutSdk\\Core\\LiveEnvironment';
                $environment = new $envClass($this->clientId, $this->secret);
                $clientClass = '\\PayPalCheckoutSdk\\Core\\PayPalHttpClient';
                $this->sdkClient = new $clientClass($environment);
            } catch (\Throwable $e) {
                $this->sdkClient = null;
            }
        }
    }

    protected function baseUrl(): string
    {
        return $this->sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    protected function authToken(): ?string
    {
        if (empty($this->clientId) || empty($this->secret)) {
            return null;
        }

        $resp = Http::withBasicAuth($this->clientId, $this->secret)
            ->asForm()
            ->post($this->baseUrl() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($resp->ok()) {
            return $resp->json('access_token');
        }

        return null;
    }

    /**
     * Create an order (returns approval URL and order id). If no credentials provided, returns a placeholder.
     */
    public function createOrder(float $amount, string $currency = 'USD', string $returnUrl = '', string $cancelUrl = ''): array
    {
        // Use SDK when available
        if ($this->sdkClient) {
            try {
                $requestClass = '\\PayPalCheckoutSdk\\Orders\\OrdersCreateRequest';
                $request = new $requestClass();
                $request->prefer('return=representation');
                $request->body = [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => number_format($amount, 2, '.', ''),
                        ],
                    ]],
                    'application_context' => [
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ];

                $response = $this->sdkClient->execute($request);
                $result = $response->result ?? null;
                $approval = null;
                foreach ($result->links ?? [] as $link) {
                    if (($link->rel ?? '') === 'approve') {
                        $approval = $link->href;
                        break;
                    }
                }

                return [
                    'id' => $result->id ?? null,
                    'status' => $result->status ?? null,
                    'approval_url' => $approval,
                    'raw' => json_decode(json_encode($result), true),
                ];
            } catch (\Throwable $e) {
                // fall through to HTTP approach
            }
        }

        $token = $this->authToken();

        if (! $token) {
            // Placeholder behavior for environments without API keys.
            $fakeId = 'PP_FAKE_' . time();
            return [
                'id' => $fakeId,
                'status' => 'CREATED',
                'approval_url' => $returnUrl ?: url('/'),
                'placeholder' => true,
            ];
        }

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => strtoupper($currency),
                    'value' => number_format($amount, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $resp = Http::withToken($token)
            ->post($this->baseUrl() . '/v2/checkout/orders', $body);

        if (! $resp->ok()) {
            return ['error' => $resp->body(), 'status' => $resp->status()];
        }

        $data = $resp->json();
        $approval = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approval = $link['href'];
                break;
            }
        }

        return [
            'id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
            'approval_url' => $approval,
            'raw' => $data,
        ];
    }

    /**
     * Capture an order by ID. Returns API response or placeholder success when keys missing.
     */
    public function captureOrder(string $orderId): array
    {
        if ($this->sdkClient) {
            try {
                $reqClass = '\\PayPalCheckoutSdk\\Orders\\OrdersCaptureRequest';
                $req = new $reqClass($orderId);
                $resp = $this->sdkClient->execute($req);
                $result = $resp->result ?? null;
                return json_decode(json_encode($result), true);
            } catch (\Throwable $e) {
                // fall back to HTTP capture
            }
        }

        $token = $this->authToken();
        if (! $token) {
            return ['id' => $orderId, 'status' => 'COMPLETED', 'placeholder' => true];
        }

        $resp = Http::withToken($token)
            ->post($this->baseUrl() . "/v2/checkout/orders/{$orderId}/capture");

        if (! $resp->ok()) {
            return ['error' => $resp->body(), 'status' => $resp->status()];
        }

        return $resp->json();
    }

    /**
     * Verify webhook signature using PayPal API. Returns true when verified or when credentials are not configured (placeholder).
     * @param array $headers
     * @param string $body
     * @return bool
     */
    public function verifyWebhook(array $headers, string $body): bool
    {
        // If no credentials or webhook id configured, treat as placeholder (return true)
        $cfg = config('payments.paypal', []);
        $webhookId = $cfg['webhook_id'] ?? '';
        $token = $this->authToken();
        if (! $token || empty($webhookId)) {
            return true;
        }

        $transmissionId = $headers['paypal-transmission-id'][0] ?? $headers['paypal-transmission-id'] ?? null;
        $transmissionTime = $headers['paypal-transmission-time'][0] ?? $headers['paypal-transmission-time'] ?? null;
        $certUrl = $headers['paypal-cert-url'][0] ?? $headers['paypal-cert-url'] ?? null;
        $authAlgo = $headers['paypal-auth-algo'][0] ?? $headers['paypal-auth-algo'] ?? null;
        $transmissionSig = $headers['paypal-transmission-sig'][0] ?? $headers['paypal-transmission-sig'] ?? null;

        $payload = [
            'transmission_id' => $transmissionId,
            'transmission_time' => $transmissionTime,
            'cert_url' => $certUrl,
            'auth_algo' => $authAlgo,
            'transmission_sig' => $transmissionSig,
            'webhook_id' => $webhookId,
            'webhook_event' => json_decode($body, true),
        ];

        $resp = Http::withToken($token)
            ->post($this->baseUrl() . '/v1/notifications/verify-webhook-signature', $payload);

        if (! $resp->ok()) {
            return false;
        }

        $data = $resp->json();
        return isset($data['verification_status']) && $data['verification_status'] === 'SUCCESS';
    }
}
