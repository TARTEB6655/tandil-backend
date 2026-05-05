<?php

namespace App\Services;

use App\Models\Setting;
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
        $fromDbId = trim((string) Setting::get('paypal_client_id', ''));
        $fromDbSecret = trim((string) Setting::get('paypal_client_secret', ''));
        $this->clientId = $fromDbId !== '' ? $fromDbId : ($cfg['client_id'] ?? '');
        $this->secret = $fromDbSecret !== '' ? $fromDbSecret : ($cfg['secret'] ?? '');
        $mode = Setting::get('paypal_mode', '');
        if ($mode === 'live') {
            $this->sandbox = false;
        } elseif ($mode === 'sandbox') {
            $this->sandbox = true;
        } else {
            $this->sandbox = (bool) ($cfg['sandbox'] ?? true);
        }

        $this->initSdkClient();
    }

    protected function initSdkClient(): void
    {
        $this->sdkClient = null;
        if ($this->clientId === '' || $this->secret === '') {
            return;
        }
        if (! class_exists('\\PayPalCheckoutSdk\\Core\\PayPalHttpClient') || ! class_exists('\\PayPalCheckoutSdk\\Core\\SandboxEnvironment')) {
            return;
        }
        try {
            $envClass = $this->sandbox ? '\\PayPalCheckoutSdk\\Core\\SandboxEnvironment' : '\\PayPalCheckoutSdk\\Core\\LiveEnvironment';
            $environment = new $envClass($this->clientId, $this->secret);
            $clientClass = '\\PayPalCheckoutSdk\\Core\\PayPalHttpClient';
            $this->sdkClient = new $clientClass($environment);
        } catch (\Throwable $e) {
            $this->sdkClient = null;
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
            ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if ($resp->ok()) {
            return $resp->json('access_token');
        }

        return null;
    }

    /**
     * Create an order (returns approval URL and order id). If no credentials provided, returns a placeholder.
     * Supports any currency: if PayPal doesn't support it (e.g. AED), we convert to USD and call PayPal with USD.
     */
    public function createOrder(
        float $amount,
        string $currency = 'USD',
        string $returnUrl = '',
        string $cancelUrl = '',
        ?string $customId = null,
        array $options = []
    ): array
    {
        $currency = strtoupper($currency);
        [$paypalCurrency, $paypalAmount] = $this->normalizeCurrencyForPayPal($amount, $currency);
        $paymentTokenId = trim((string) ($options['payment_token_id'] ?? ''));
        $storeInVault = (bool) ($options['store_in_vault'] ?? false);

        $purchaseUnit = [
            'amount' => [
                'currency_code' => $paypalCurrency,
                'value' => number_format($paypalAmount, 2, '.', ''),
            ],
        ];
        if ($customId !== null && $customId !== '') {
            $purchaseUnit['custom_id'] = (string) $customId;
        }

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [$purchaseUnit],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];
        if ($paymentTokenId !== '') {
            $body['payment_source'] = [
                'token' => [
                    'id' => $paymentTokenId,
                    'type' => 'PAYMENT_METHOD_TOKEN',
                ],
            ];
        } elseif ($storeInVault) {
            $body['payment_source'] = [
                'paypal' => [
                    'attributes' => [
                        'vault' => [
                            'store_in_vault' => 'ON_SUCCESS',
                            'usage_type' => 'MERCHANT',
                            'customer_type' => 'CONSUMER',
                        ],
                    ],
                ],
            ];
        }

        // Use SDK when available
        if ($this->sdkClient) {
            try {
                $requestClass = '\\PayPalCheckoutSdk\\Orders\\OrdersCreateRequest';
                $request = new $requestClass;
                $request->prefer('return=representation');
                $request->body = $body;

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
            $fakeId = 'PP_FAKE_'.time();

            return [
                'id' => $fakeId,
                'status' => 'CREATED',
                'approval_url' => $returnUrl ?: url('/'),
                'placeholder' => true,
                'store_in_vault' => $storeInVault,
                'payment_token_id' => $paymentTokenId !== '' ? $paymentTokenId : null,
            ];
        }

        $resp = Http::withToken($token)
            ->post($this->baseUrl().'/v2/checkout/orders', $body);

        $data = $resp->json();
        $approval = null;
        foreach ($data['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'approve') {
                $approval = $link['href'];
                break;
            }
        }

        if ($resp->successful() || (isset($data['status']) && ($data['status'] === 'CREATED' || $data['status'] === 'APPROVED') && $approval)) {
            return [
                'id' => $data['id'] ?? null,
                'status' => $data['status'] ?? null,
                'approval_url' => $approval,
                'raw' => $data,
            ];
        }

        return ['error' => $resp->body(), 'status' => $resp->status()];
    }

    /**
     * Return currency and amount to send to PayPal. If requested currency is not supported (e.g. AED), convert to USD.
     */
    protected function normalizeCurrencyForPayPal(float $amount, string $currency): array
    {
        $supported = config('payments.paypal.supported_currencies', ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']);
        $fallback = config('payments.paypal.fallback_currency', 'USD');
        $rates = config('payments.paypal.exchange_rates_to_usd', []);

        if (in_array($currency, $supported, true)) {
            return [$currency, $amount];
        }

        $rate = $rates[$currency] ?? null;
        if ($rate !== null && $rate > 0) {
            return [$fallback, round($amount * $rate, 2)];
        }

        return [$fallback, $amount];
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
            return [
                'id' => $orderId,
                'status' => 'COMPLETED',
                'placeholder' => true,
                'payment_source' => [
                    'paypal' => [
                        'email_address' => 'paypal-user@example.com',
                        'attributes' => [
                            'vault' => [
                                'id' => 'PMT_PLACEHOLDER_'.substr(sha1($orderId), 0, 12),
                                'status' => 'VAULTED',
                            ],
                        ],
                    ],
                ],
            ];
        }

        // PayPal capture endpoint expects a JSON object body ("{}"), not an empty array/string.
        // Sending an implicit empty payload can trigger MALFORMED_REQUEST_JSON.
        $resp = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->withBody('{}', 'application/json')
            ->send('POST', $this->baseUrl()."/v2/checkout/orders/{$orderId}/capture");

        if (! $resp->ok()) {
            return ['error' => $resp->body(), 'status' => $resp->status()];
        }

        return $resp->json();
    }

    /**
     * @return array{
     *   provider_method_id?:string|null,
     *   provider_customer_id?:string|null,
     *   email?:string|null,
     *   brand?:string|null,
     *   last4?:string|null,
     *   expiry_month?:int|null,
     *   expiry_year?:int|null,
     *   raw?:array<string,mixed>
     * }|null
     */
    public function extractVaultedPaymentMethod(array $capture): ?array
    {
        $paypal = $capture['payment_source']['paypal'] ?? null;
        if (! is_array($paypal)) {
            return null;
        }

        $vaultId = $paypal['attributes']['vault']['id'] ?? null;
        if (! is_string($vaultId) || $vaultId === '') {
            return null;
        }

        $card = $paypal['card'] ?? null;
        $expiryMonth = null;
        $expiryYear = null;
        if (is_array($card)) {
            $expiry = (string) ($card['expiry'] ?? '');
            if (preg_match('/^(\d{4})-(\d{2})$/', $expiry, $m)) {
                $expiryYear = (int) $m[1];
                $expiryMonth = (int) $m[2];
            }
        }

        return [
            'provider_method_id' => $vaultId,
            'provider_customer_id' => isset($paypal['account_id']) ? (string) $paypal['account_id'] : null,
            'email' => isset($paypal['email_address']) ? (string) $paypal['email_address'] : null,
            'brand' => is_array($card) && isset($card['brand']) ? (string) $card['brand'] : null,
            'last4' => is_array($card) && isset($card['last_digits']) ? (string) $card['last_digits'] : null,
            'expiry_month' => $expiryMonth,
            'expiry_year' => $expiryYear,
            'raw' => $paypal,
        ];
    }

    /**
     * Verify webhook signature using PayPal API. Returns true when verified or when credentials are not configured (placeholder).
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
            ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', $payload);

        if (! $resp->ok()) {
            return false;
        }

        $data = $resp->json();

        return isset($data['verification_status']) && $data['verification_status'] === 'SUCCESS';
    }
}
