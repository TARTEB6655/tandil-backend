<?php

namespace App\Services\Auth;

use App\Contracts\PasswordResetOtpHandler;
use App\Support\TwilioHttpCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TwilioVerifyPasswordResetOtpHandler implements PasswordResetOtpHandler
{
    public function usesExternalVerification(): bool
    {
        return true;
    }

    public function initiate(string $toE164, int $otpTtlSeconds): array
    {
        if (! TwilioHttpCredentials::isVerifyConfigured()) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error' => 'Twilio Verify is not configured.',
                'otp_plaintext' => null,
            ];
        }

        $serviceSid = (string) config('services.sms.twilio.verify_service_sid');
        [$user, $pass] = TwilioHttpCredentials::basicAuth();

        $response = Http::withBasicAuth($user, $pass)
            ->asForm()
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications", [
                'To' => $toE164,
                'Channel' => 'sms',
            ]);

        if (! $response->successful()) {
            Log::warning('twilio_verify.start_failed', [
                'status' => $response->status(),
                'body' => $response->json('message') ?? $response->body(),
            ]);

            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error' => $response->json('message') ?? 'Could not start verification.',
                'otp_plaintext' => null,
            ];
        }

        $status = (string) $response->json('status', '');
        if (! in_array($status, ['pending', 'approved'], true)) {
            return [
                'success' => false,
                'provider' => 'twilio_verify',
                'error' => 'Unexpected verification status: '.$status,
                'otp_plaintext' => null,
            ];
        }

        return [
            'success' => true,
            'provider' => 'twilio_verify',
            'error' => null,
            'otp_plaintext' => null,
        ];
    }

    public function validateCode(string $toE164, string $code, string $storedOtpHash): bool
    {
        if (! TwilioHttpCredentials::isVerifyConfigured()) {
            return false;
        }

        $serviceSid = (string) config('services.sms.twilio.verify_service_sid');
        [$user, $pass] = TwilioHttpCredentials::basicAuth();

        $response = Http::withBasicAuth($user, $pass)
            ->asForm()
            ->post("https://verify.twilio.com/v2/Services/{$serviceSid}/VerificationCheck", [
                'To' => $toE164,
                'Code' => trim($code),
            ]);

        if ($response->status() === 404) {
            return false;
        }

        if (! $response->successful()) {
            Log::warning('twilio_verify.check_failed', [
                'status' => $response->status(),
                'body' => $response->json('message') ?? $response->body(),
            ]);

            return false;
        }

        return (string) $response->json('status') === 'approved';
    }
}
