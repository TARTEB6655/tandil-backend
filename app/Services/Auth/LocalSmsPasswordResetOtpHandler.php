<?php

namespace App\Services\Auth;

use App\Contracts\PasswordResetOtpHandler;
use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Hash;

final class LocalSmsPasswordResetOtpHandler implements PasswordResetOtpHandler
{
    public function __construct(private readonly SmsSender $sms) {}

    public function usesExternalVerification(): bool
    {
        return false;
    }

    public function initiate(string $toE164, int $otpTtlSeconds): array
    {
        $otp = (string) random_int(100000, 999999);
        $template = (string) config('services.sms.password_reset.message_template', 'Your TANDIL verification code is :code. Valid for :minutes minutes.');
        $message = str_replace(
            [':code', ':minutes'],
            [$otp, (string) max(1, (int) ceil($otpTtlSeconds / 60))],
            $template
        );

        $smsResult = $this->sms->send($toE164, $message);
        if (! ($smsResult['success'] ?? false)) {
            return [
                'success' => false,
                'provider' => (string) ($smsResult['provider'] ?? 'sms'),
                'error' => (string) ($smsResult['error'] ?? 'Could not send SMS.'),
                'otp_plaintext' => null,
            ];
        }

        return [
            'success' => true,
            'provider' => (string) ($smsResult['provider'] ?? 'sms'),
            'error' => null,
            'otp_plaintext' => $otp,
        ];
    }

    public function validateCode(string $toE164, string $code, string $storedOtpHash): bool
    {
        return Hash::check(trim($code), $storedOtpHash);
    }
}
