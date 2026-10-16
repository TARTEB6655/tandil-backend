<?php

namespace App\Contracts;

interface PasswordResetOtpHandler
{
    /**
     * When true, OTP is issued and checked by the provider (e.g. Twilio Verify), not local hashes.
     */
    public function usesExternalVerification(): bool;

    /**
     * Start OTP delivery (local SMS with app-generated code, or provider-managed verification).
     *
     * @return array{success: bool, provider: string, error: ?string, otp_plaintext: ?string}
     */
    public function initiate(string $toE164, int $otpTtlSeconds): array;

    /**
     * Validate the user-entered code (local hash or provider check).
     */
    public function validateCode(string $toE164, string $code, string $storedOtpHash): bool;
}
