<?php

namespace App\Services\Auth;

use App\Contracts\PasswordResetOtpHandler;
use App\Models\MobilePasswordResetChallenge;
use App\Models\User;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MobilePasswordResetService
{
    public function __construct(private readonly PasswordResetOtpHandler $otpHandler) {}

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, message: string, errors?: array<string, list<string>>}
     */
    public function sendOtp(string $phoneInput, string $portal, ?string $ip = null): array
    {
        $phoneDigits = PhoneNumberNormalizer::digitsKey($phoneInput);
        if ($phoneDigits === null) {
            return $this->fail('Enter a valid mobile number with country code.', 422, [
                'phone' => ['Enter a valid mobile number with country code.'],
            ]);
        }

        $user = $this->findActiveUserByPhone($phoneDigits, $portal);
        if ($user === null) {
            Log::info('password_reset.phone_not_found', [
                'portal' => $portal,
                'phone_digits' => substr($phoneDigits, 0, 5).'***',
                'ip' => $ip,
            ]);

            return $this->fail('No account is registered with this mobile number for the selected role.', 404, [
                'phone' => ['No account is registered with this mobile number for the selected role.'],
            ]);
        }

        if (trim((string) $user->phone) === '') {
            return $this->fail('This account has no registered mobile number. Contact support.', 422, [
                'phone' => ['This account has no registered mobile number.'],
            ]);
        }

        $windowStart = now()->subHour();
        $recentSends = MobilePasswordResetChallenge::query()
            ->where('phone_digits', $phoneDigits)
            ->where('portal', $portal)
            ->where('created_at', '>=', $windowStart)
            ->sum('send_count');

        $maxPerHour = (int) config('services.sms.password_reset.max_sends_per_hour', 3);
        if ($recentSends >= $maxPerHour) {
            return $this->fail('Too many verification codes requested. Try again later.', 429, [
                'phone' => ['Too many verification codes requested. Try again later.'],
            ]);
        }

        $active = $this->activeChallenge($phoneDigits, $portal);
        $resendCooldown = (int) config('services.sms.password_reset.resend_cooldown_seconds', 60);
        if ($active !== null && $active->last_sent_at->diffInSeconds(now()) < $resendCooldown) {
            $wait = $resendCooldown - $active->last_sent_at->diffInSeconds(now());

            return $this->fail('Please wait before requesting another code.', 429, [
                'phone' => ["Please wait {$wait} seconds before requesting another code."],
            ]);
        }

        if ($active !== null && $active->isLocked()) {
            return $this->fail('Too many failed attempts. Try again later.', 423, [
                'otp' => ['Too many failed attempts. Try again later.'],
            ]);
        }

        $otpTtl = (int) config('services.sms.password_reset.otp_ttl_seconds', 600);
        $toE164 = '+'.$phoneDigits;

        $delivery = $this->otpHandler->initiate($toE164, $otpTtl);
        Log::info('password_reset.otp_sent', [
            'user_id' => $user->id,
            'portal' => $portal,
            'otp_provider' => $delivery['provider'] ?? null,
            'otp_success' => $delivery['success'] ?? false,
            'external_verification' => $this->otpHandler->usesExternalVerification(),
            'ip' => $ip,
        ]);

        if (! ($delivery['success'] ?? false)) {
            return $this->fail('Could not send SMS. Please try again later.', 503);
        }

        $otpHash = $delivery['otp_plaintext'] !== null
            ? Hash::make($delivery['otp_plaintext'])
            : Hash::make('twilio-verify:'.Str::random(32));

        if ($active !== null) {
            $active->forceFill([
                'user_id' => $user->id,
                'otp_hash' => $otpHash,
                'otp_expires_at' => now()->addSeconds($otpTtl),
                'otp_attempts' => 0,
                'send_count' => $active->send_count + 1,
                'last_sent_at' => now(),
                'locked_until' => null,
                'reset_token_hash' => null,
                'reset_token_expires_at' => null,
                'request_ip' => $ip,
            ])->save();
            $challenge = $active;
        } else {
            MobilePasswordResetChallenge::query()
                ->where('phone_digits', $phoneDigits)
                ->where('portal', $portal)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            $challenge = MobilePasswordResetChallenge::create([
                'user_id' => $user->id,
                'phone_digits' => $phoneDigits,
                'portal' => $portal,
                'otp_hash' => $otpHash,
                'otp_expires_at' => now()->addSeconds($otpTtl),
                'send_count' => 1,
                'last_sent_at' => now(),
                'request_ip' => $ip,
            ]);
        }

        return [
            'ok' => true,
            'data' => [
                'phone_masked' => PhoneNumberNormalizer::maskForDisplay($user->phone),
                'expires_in_seconds' => $otpTtl,
                'resend_available_in_seconds' => $resendCooldown,
            ],
        ];
    }

    /**
     * @return array{ok: true, data: array<string, mixed>}|array{ok: false, status: int, message: string, errors?: array<string, list<string>>}
     */
    public function verifyOtp(string $phoneInput, string $portal, string $otp): array
    {
        $phoneDigits = PhoneNumberNormalizer::digitsKey($phoneInput);
        if ($phoneDigits === null) {
            return $this->fail('Enter a valid mobile number.', 422);
        }

        $challenge = $this->activeChallenge($phoneDigits, $portal);
        if ($challenge === null) {
            return $this->fail('No active verification request. Request a new code.', 404, [
                'otp' => ['No active verification request. Request a new code.'],
            ]);
        }

        if ($challenge->isLocked()) {
            return $this->fail('Too many failed attempts. Request a new code later.', 423);
        }

        if ($challenge->isOtpExpired()) {
            return $this->fail('Verification code expired. Request a new code.', 410, [
                'otp' => ['Verification code expired. Request a new code.'],
            ]);
        }

        if (! $this->otpHandler->validateCode('+'.$phoneDigits, $otp, (string) $challenge->otp_hash)) {
            $challenge->otp_attempts++;
            $maxAttempts = (int) config('services.sms.password_reset.max_verify_attempts', 5);
            if ($challenge->otp_attempts >= $maxAttempts) {
                $lockMinutes = (int) config('services.sms.password_reset.lockout_minutes', 15);
                $challenge->locked_until = now()->addMinutes($lockMinutes);
            }
            $challenge->save();

            Log::warning('password_reset.otp_failed', [
                'challenge_id' => $challenge->id,
                'user_id' => $challenge->user_id,
                'attempts' => $challenge->otp_attempts,
            ]);

            return $this->fail('Incorrect verification code.', 422, [
                'otp' => ['Incorrect verification code.'],
            ]);
        }

        $resetToken = Str::random(64);
        $tokenTtl = (int) config('services.sms.password_reset.reset_token_ttl_seconds', 900);
        $challenge->forceFill([
            'reset_token_hash' => Hash::make($resetToken),
            'reset_token_expires_at' => now()->addSeconds($tokenTtl),
        ])->save();

        Log::info('password_reset.otp_verified', [
            'challenge_id' => $challenge->id,
            'user_id' => $challenge->user_id,
        ]);

        return [
            'ok' => true,
            'data' => [
                'reset_token' => $resetToken,
                'expires_in_seconds' => $tokenTtl,
            ],
        ];
    }

    /**
     * @return array{ok: true, message: string}|array{ok: false, status: int, message: string, errors?: array<string, list<string>>}
     */
    public function resetPassword(string $resetToken, string $password): array
    {
        if (trim($resetToken) === '') {
            return $this->fail('Reset token is required.', 422);
        }

        $candidates = MobilePasswordResetChallenge::query()
            ->whereNull('consumed_at')
            ->whereNotNull('reset_token_hash')
            ->where('reset_token_expires_at', '>', now())
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $challenge = null;
        foreach ($candidates as $row) {
            if (Hash::check($resetToken, (string) $row->reset_token_hash)) {
                $challenge = $row;
                break;
            }
        }

        if ($challenge === null) {
            return $this->fail('Invalid or expired reset token. Start again from step 1.', 401, [
                'reset_token' => ['Invalid or expired reset token.'],
            ]);
        }

        $user = $challenge->user;
        if ($user === null || $user->status !== 'active') {
            return $this->fail('Account is not active.', 403);
        }

        $user->password = $password;
        $user->save();

        $challenge->forceFill(['consumed_at' => now()])->save();
        MobilePasswordResetChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        Log::info('password_reset.completed', [
            'user_id' => $user->id,
            'portal' => $challenge->portal,
        ]);

        return [
            'ok' => true,
            'message' => 'Password updated successfully. You can sign in with your new password.',
        ];
    }

    private function findActiveUserByPhone(string $phoneDigits, string $portal): ?User
    {
        $variants = PhoneNumberNormalizer::lookupVariants('+'.$phoneDigits);

        return User::query()
            ->where('status', 'active')
            ->where(function ($q) use ($portal) {
                $q->where('role', $portal);
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    $q->orWhere('phone', $variant);
                }
            })
            ->orderBy('id')
            ->first();
    }

    private function activeChallenge(string $phoneDigits, string $portal): ?MobilePasswordResetChallenge
    {
        return MobilePasswordResetChallenge::query()
            ->where('phone_digits', $phoneDigits)
            ->where('portal', $portal)
            ->whereNull('consumed_at')
            ->whereNull('reset_token_hash')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, list<string>>|null  $errors
     * @return array{ok: false, status: int, message: string, errors?: array<string, list<string>>}
     */
    private function fail(string $message, int $status, ?array $errors = null): array
    {
        $payload = ['ok' => false, 'status' => $status, 'message' => $message];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $payload;
    }
}
