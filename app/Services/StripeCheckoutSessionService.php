<?php

namespace App\Services;

use App\Models\Order;
use App\Support\StripeCredentials;
use Illuminate\Support\Facades\Http;

class StripeCheckoutSessionService
{
    /**
     * Create a Stripe Checkout Session for a shop order (one line item = order total).
     *
     * @return array{url?: string, session_id?: string}|array{error: string}
     */
    public function createForOrder(Order $order, string $successUrl, string $cancelUrl, string $currency): array
    {
        $secret = StripeCredentials::secretKey();
        if ($secret === '') {
            return ['error' => 'Stripe secret not configured'];
        }

        $currencyLower = strtolower($currency);
        $unitAmount = (int) round((float) $order->total_amount * 100);
        if ($unitAmount < 1) {
            return ['error' => 'Invalid amount'];
        }

        $params = [
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata[order_id]' => (string) $order->id,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $currencyLower,
            'line_items[0][price_data][product_data][name]' => 'Order #'.$order->id,
            'line_items[0][price_data][unit_amount]' => $unitAmount,
        ];

        $resp = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', $params);

        if (! $resp->ok()) {
            $msg = $resp->json('error.message') ?? $resp->body();

            return ['error' => is_string($msg) ? $msg : json_encode($msg)];
        }

        $data = $resp->json();

        return [
            'url' => $data['url'] ?? null,
            'session_id' => $data['id'] ?? null,
        ];
    }
}
