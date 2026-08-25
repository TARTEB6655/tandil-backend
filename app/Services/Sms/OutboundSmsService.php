<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin outbound SMS adapter for delivery OTP (and future SMS use).
 * Default driver logs the payload; configure services.sms for a real gateway.
 */
class OutboundSmsService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function send(string $to, string $message, array $context = []): bool
    {
        $to = trim($to);
        if ($to === '' || trim($message) === '') {
            return false;
        }

        $driver = strtolower((string) config('services.sms.driver', 'log'));

        return match ($driver) {
            'http' => $this->sendViaHttp($to, $message, $context),
            default => $this->sendViaLog($to, $message, $context),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sendViaLog(string $to, string $message, array $context): bool
    {
        Log::info('sms.outbound', [
            'to' => $to,
            'sender_id' => config('services.sms.sender_id'),
            'message' => $message,
            'context' => $context,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function sendViaHttp(string $to, string $message, array $context): bool
    {
        $url = trim((string) config('services.sms.http_url', ''));
        if ($url === '') {
            return $this->sendViaLog($to, $message, $context);
        }

        try {
            $headers = ['Accept' => 'application/json'];
            $token = trim((string) config('services.sms.http_token', ''));
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $response = Http::timeout(8)
                ->withHeaders($headers)
                ->post($url, [
                    'to' => $to,
                    'message' => $message,
                    'sender_id' => config('services.sms.sender_id'),
                    'context' => $context,
                ]);

            if (! $response->successful()) {
                Log::warning('sms.outbound.http_failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'context' => $context,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('sms.outbound.http_exception', [
                'to' => $to,
                'error' => $e->getMessage(),
                'context' => $context,
            ]);

            return false;
        }
    }
}
