<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Stripe keys: Admin → Payments (settings table) first, then .env for local/testing.
 */
final class StripeCredentials
{
    public static function secretKey(): string
    {
        $fromDb = self::normalizeKey((string) Setting::get('stripe_secret_key', ''));

        return $fromDb !== '' ? $fromDb : self::normalizeKey((string) config('services.stripe.secret', ''));
    }

    public static function publishableKey(): string
    {
        $fromDb = self::normalizeKey((string) Setting::get('stripe_public_key', ''));

        return $fromDb !== '' ? $fromDb : self::normalizeKey((string) config('services.stripe.key', ''));
    }

    public static function webhookSecret(): string
    {
        $fromDb = self::normalizeKey((string) Setting::get('stripe_webhook_secret', ''));

        return $fromDb !== '' ? $fromDb : self::normalizeKey((string) config('services.stripe.webhook_secret', ''));
    }

    public static function normalizeKey(string $key): string
    {
        $key = trim($key);
        $key = trim($key, " \t\n\r\0\x0B\"'");

        return $key;
    }

    /**
     * @return list<string>
     */
    public static function validateKeyPair(?string $secret, ?string $publishable): array
    {
        $secret = self::normalizeKey((string) $secret);
        $publishable = self::normalizeKey((string) $publishable);
        $issues = [];

        if ($secret === '') {
            $issues[] = 'Stripe secret key is required.';
        }
        if ($publishable === '') {
            $issues[] = 'Stripe publishable key is required.';
        }

        $secretMode = self::keyMode($secret);
        $publishableMode = self::keyMode($publishable);

        if ($secret !== '' && $secretMode === null) {
            $issues[] = 'Stripe secret key must start with sk_test_ or sk_live_.';
        }
        if ($publishable !== '' && $publishableMode === null) {
            $issues[] = 'Stripe publishable key must start with pk_test_ or pk_live_.';
        }
        if ($secretMode !== null && $publishableMode !== null && $secretMode !== $publishableMode) {
            $issues[] = "Stripe keys must both be {$secretMode} or both be {$publishableMode}. You cannot mix test and live keys.";
        }

        return $issues;
    }

    public static function adminStripeEnabled(): bool
    {
        $v = Setting::get('stripe_enabled', false);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN)
            || $v === '1'
            || $v === 1
            || $v === true;
    }

    /**
     * Shop/API may use Stripe when a secret key is configured (DB admin keys or .env).
     * If the `stripe_enabled` setting row exists in the database and is explicitly off,
     * checkout stays disabled even when keys are present (admin kill-switch).
     * When that row does not exist, keys in .env alone are enough (e.g. production).
     */
    public static function isStripeUsableForCheckout(): bool
    {
        if (self::secretKey() === '') {
            return false;
        }

        $row = Setting::query()->where('key', 'stripe_enabled')->first();
        if ($row !== null) {
            return self::adminStripeEnabled();
        }

        return true;
    }

    /** @return 'live'|'test'|'unknown' */
    public static function mode(): string
    {
        $secret = self::secretKey();
        if (str_starts_with($secret, 'sk_live_')) {
            return 'live';
        }
        if (str_starts_with($secret, 'sk_test_')) {
            return 'test';
        }

        return 'unknown';
    }

    /**
     * Changes when admin swaps Stripe accounts or switches test/live keys.
     */
    public static function accountFingerprint(): string
    {
        return hash('sha256', self::secretKey().'|'.self::publishableKey());
    }

    /** @return 'database'|'env'|'none' */
    public static function secretKeySource(): string
    {
        $fromDb = trim((string) Setting::get('stripe_secret_key', ''));

        return $fromDb !== '' ? 'database' : (trim((string) config('services.stripe.secret', '')) !== '' ? 'env' : 'none');
    }

    /** @return 'database'|'env'|'none' */
    public static function publishableKeySource(): string
    {
        $fromDb = trim((string) Setting::get('stripe_public_key', ''));

        return $fromDb !== '' ? 'database' : (trim((string) config('services.stripe.key', '')) !== '' ? 'env' : 'none');
    }

    public static function keyMode(?string $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }
        if (str_starts_with($key, 'sk_live_') || str_starts_with($key, 'pk_live_')) {
            return 'live';
        }
        if (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'pk_test_')) {
            return 'test';
        }

        return null;
    }

    /**
     * Hard failures — checkout must not proceed.
     *
     * @return list<string>
     */
    public static function blockingConfigurationIssues(): array
    {
        return self::validateKeyPair(self::secretKey(), self::publishableKey());
    }

    /**
     * Informational notes for diagnostics only (do not block checkout).
     *
     * @return list<string>
     */
    public static function configurationNotes(): array
    {
        $notes = [];

        if (self::secretKeySource() === 'database' && trim((string) config('services.stripe.secret', '')) !== '') {
            $notes[] = 'Admin dashboard Stripe keys override .env. Updating .env alone has no effect while Admin keys are saved.';
        }

        return $notes;
    }

    /**
     * @return list<string> Blocking issues + informational notes (for API diagnostics).
     */
    public static function configurationIssues(): array
    {
        return array_values(array_merge(
            self::blockingConfigurationIssues(),
            self::configurationNotes()
        ));
    }

    /**
     * Safe prefix for API diagnostics (never expose full secret).
     */
    public static function maskedSecretPrefix(): ?string
    {
        $secret = self::secretKey();
        if ($secret === '') {
            return null;
        }

        return substr($secret, 0, 12).'…';
    }

    public static function maskedPublishablePrefix(): ?string
    {
        $key = self::publishableKey();
        if ($key === '') {
            return null;
        }

        return substr($key, 0, 12).'…';
    }

    public static function keysVersion(): int
    {
        return (int) Setting::get('stripe_keys_version', 0);
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileStripeConfig(): array
    {
        return [
            'enabled' => self::isStripeUsableForCheckout(),
            'publishable_key' => self::publishableKey(),
            'stripe_mode' => self::mode(),
            'keys_version' => self::keysVersion(),
            'secret_key_prefix' => self::maskedSecretPrefix(),
            'publishable_key_prefix' => self::maskedPublishablePrefix(),
            'configuration_issues' => self::blockingConfigurationIssues(),
            'configuration_notes' => self::configurationNotes(),
        ];
    }

    public static function forgetCachedSettings(): void
    {
        foreach (['stripe_secret_key', 'stripe_public_key', 'stripe_webhook_secret', 'stripe_enabled', 'stripe_keys_version'] as $key) {
            \Illuminate\Support\Facades\Cache::forget('setting:'.$key);
        }
    }
}
