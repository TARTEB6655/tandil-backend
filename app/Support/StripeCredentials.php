<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Stripe keys: Admin → Payments (settings table) first, then .env.
 * Supports storing both test and live key pairs; active mode selects which pair is used.
 */
final class StripeCredentials
{
    private static bool $legacyMigrated = false;

    /** @return 'live'|'test' */
    public static function activeMode(): string
    {
        self::migrateLegacyKeysIfNeeded();

        $mode = strtolower(trim((string) Setting::get('stripe_mode', '')));
        if (in_array($mode, ['live', 'test'], true)) {
            return $mode;
        }

        $envMode = strtolower(trim((string) config('services.stripe.mode', '')));
        if (in_array($envMode, ['live', 'test'], true)) {
            return $envMode;
        }

        $secret = self::storedSecretForMode('live');
        if ($secret !== '') {
            return 'live';
        }

        return 'test';
    }

    public static function secretKey(): string
    {
        return self::keysForMode(self::activeMode())['secret'];
    }

    public static function publishableKey(): string
    {
        return self::keysForMode(self::activeMode())['public'];
    }

    public static function webhookSecret(): string
    {
        self::migrateLegacyKeysIfNeeded();

        $mode = self::activeMode();
        $fromDb = self::normalizeKey((string) Setting::get(self::settingPrefix($mode).'webhook_secret', ''));
        if ($fromDb !== '') {
            return $fromDb;
        }

        $legacy = self::normalizeKey((string) Setting::get('stripe_webhook_secret', ''));
        if ($legacy !== '') {
            return $legacy;
        }

        $envKey = $mode === 'live'
            ? config('services.stripe.live_webhook_secret')
            : config('services.stripe.test_webhook_secret');

        return self::normalizeKey((string) ($envKey ?? '')) ?: self::normalizeKey((string) config('services.stripe.webhook_secret', ''));
    }

    /**
     * @return array{public: string, secret: string}
     */
    public static function keysForMode(string $mode): array
    {
        self::migrateLegacyKeysIfNeeded();

        $mode = $mode === 'live' ? 'live' : 'test';
        $public = self::storedPublicForMode($mode);
        $secret = self::storedSecretForMode($mode);

        if ($public === '' && $secret === '') {
            $envPublic = $mode === 'live'
                ? config('services.stripe.live_key')
                : config('services.stripe.test_key');
            $envSecret = $mode === 'live'
                ? config('services.stripe.live_secret')
                : config('services.stripe.test_secret');

            $public = self::normalizeKey((string) ($envPublic ?? ''));
            $secret = self::normalizeKey((string) ($envSecret ?? ''));

            if ($public === '' && $secret === '' && self::activeMode() === $mode) {
                $public = self::normalizeKey((string) config('services.stripe.key', ''));
                $secret = self::normalizeKey((string) config('services.stripe.secret', ''));
            }
        }

        return ['public' => $public, 'secret' => $secret];
    }

    public static function storedPublicForMode(string $mode): string
    {
        self::migrateLegacyKeysIfNeeded();

        return self::normalizeKey((string) Setting::get(self::settingPrefix($mode).'public_key', ''));
    }

    public static function storedSecretForMode(string $mode): string
    {
        self::migrateLegacyKeysIfNeeded();

        return self::normalizeKey((string) Setting::get(self::settingPrefix($mode).'secret_key', ''));
    }

    /**
     * @return array{public_key: string, has_secret: bool, secret_prefix: ?string, webhook_secret: string}
     */
    public static function adminModeSettings(string $mode): array
    {
        $keys = self::keysForMode($mode === 'live' ? 'live' : 'test');
        $secret = $keys['secret'];

        return [
            'public_key' => $keys['public'],
            'has_secret' => $secret !== '',
            'secret_prefix' => $secret !== '' ? substr($secret, 0, 12).'…' : null,
            'webhook_secret' => self::normalizeKey((string) Setting::get(self::settingPrefix($mode).'webhook_secret', '')),
        ];
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

    /**
     * @return list<string>
     */
    public static function validateModeSlot(string $slotMode, ?string $secret, ?string $publishable): array
    {
        $secret = self::normalizeKey((string) $secret);
        $publishable = self::normalizeKey((string) $publishable);

        if ($secret === '' && $publishable === '') {
            return [];
        }

        $issues = self::validateKeyPair($secret, $publishable);
        $expected = $slotMode === 'live' ? 'live' : 'test';

        if ($secret !== '' && self::keyMode($secret) !== $expected) {
            $issues[] = ucfirst($slotMode).' secret key must start with sk_'.$expected.'_.';
        }
        if ($publishable !== '' && self::keyMode($publishable) !== $expected) {
            $issues[] = ucfirst($slotMode).' publishable key must start with pk_'.$expected.'_.';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array{public: string, secret: string}
     */
    public static function applyModeKeyUpdates(string $mode, string $publicInput, string $secretInput): array
    {
        $mode = $mode === 'live' ? 'live' : 'test';
        $prefix = self::settingPrefix($mode);

        $public = self::normalizeKey($publicInput);
        $secret = self::normalizeKey($secretInput);

        if ($public !== '') {
            Setting::set($prefix.'public_key', $public, 'text', 'payment');
        }
        if ($secret !== '') {
            Setting::set($prefix.'secret_key', $secret, 'text', 'payment');
        }

        return [
            'public' => $public !== '' ? $public : self::storedPublicForMode($mode),
            'secret' => $secret !== '' ? $secret : self::storedSecretForMode($mode),
        ];
    }

    public static function adminStripeEnabled(): bool
    {
        $v = Setting::get('stripe_enabled', false);

        return filter_var($v, FILTER_VALIDATE_BOOLEAN)
            || $v === '1'
            || $v === 1
            || $v === true;
    }

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
        $active = self::activeMode();

        return in_array($active, ['live', 'test'], true) ? $active : 'unknown';
    }

    public static function accountFingerprint(): string
    {
        return hash('sha256', self::activeMode().'|'.self::secretKey().'|'.self::publishableKey());
    }

    /** @return 'database'|'env'|'none' */
    public static function secretKeySource(): string
    {
        if (self::storedSecretForMode(self::activeMode()) !== '') {
            return 'database';
        }

        return self::normalizeKey((string) config('services.stripe.secret', '')) !== '' ? 'env' : 'none';
    }

    /** @return 'database'|'env'|'none' */
    public static function publishableKeySource(): string
    {
        if (self::storedPublicForMode(self::activeMode()) !== '') {
            return 'database';
        }

        return self::normalizeKey((string) config('services.stripe.key', '')) !== '' ? 'env' : 'none';
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
     * @return list<string>
     */
    public static function blockingConfigurationIssues(): array
    {
        return self::validateKeyPair(self::secretKey(), self::publishableKey());
    }

    /**
     * @return list<string>
     */
    public static function configurationNotes(): array
    {
        $notes = [];

        $hasDbKeys = self::storedPublicForMode('test') !== ''
            || self::storedSecretForMode('test') !== ''
            || self::storedPublicForMode('live') !== ''
            || self::storedSecretForMode('live') !== '';

        if ($hasDbKeys && (
            self::normalizeKey((string) config('services.stripe.secret', '')) !== ''
            || self::normalizeKey((string) config('services.stripe.test_secret', '')) !== ''
            || self::normalizeKey((string) config('services.stripe.live_secret', '')) !== ''
        )) {
            $notes[] = 'Admin dashboard Stripe keys override .env. Use the Test/Live mode toggle here; .env keys are only a fallback when admin keys are empty.';
        }

        return $notes;
    }

    /**
     * @return list<string>
     */
    public static function configurationIssues(): array
    {
        return array_values(array_merge(
            self::blockingConfigurationIssues(),
            self::configurationNotes()
        ));
    }

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
        foreach ([
            'stripe_mode',
            'stripe_test_public_key',
            'stripe_test_secret_key',
            'stripe_test_webhook_secret',
            'stripe_live_public_key',
            'stripe_live_secret_key',
            'stripe_live_webhook_secret',
            'stripe_public_key',
            'stripe_secret_key',
            'stripe_webhook_secret',
            'stripe_enabled',
            'stripe_keys_version',
        ] as $key) {
            \Illuminate\Support\Facades\Cache::forget('setting:'.$key);
        }
    }

    private static function settingPrefix(string $mode): string
    {
        return $mode === 'live' ? 'stripe_live_' : 'stripe_test_';
    }

    private static function migrateLegacyKeysIfNeeded(): void
    {
        if (self::$legacyMigrated) {
            return;
        }
        self::$legacyMigrated = true;

        $legacyPublic = self::normalizeKey((string) Setting::get('stripe_public_key', ''));
        $legacySecret = self::normalizeKey((string) Setting::get('stripe_secret_key', ''));
        if ($legacyPublic === '' && $legacySecret === '') {
            return;
        }

        $legacyMode = self::keyMode($legacySecret) ?? self::keyMode($legacyPublic) ?? 'test';
        $prefix = self::settingPrefix($legacyMode);

        if (self::normalizeKey((string) Setting::get($prefix.'public_key', '')) === '' && $legacyPublic !== '') {
            Setting::set($prefix.'public_key', $legacyPublic, 'text', 'payment');
        }
        if (self::normalizeKey((string) Setting::get($prefix.'secret_key', '')) === '' && $legacySecret !== '') {
            Setting::set($prefix.'secret_key', $legacySecret, 'text', 'payment');
        }

        $legacyWebhook = self::normalizeKey((string) Setting::get('stripe_webhook_secret', ''));
        if ($legacyWebhook !== '' && self::normalizeKey((string) Setting::get($prefix.'webhook_secret', '')) === '') {
            Setting::set($prefix.'webhook_secret', $legacyWebhook, 'text', 'payment');
        }

        $currentMode = strtolower(trim((string) Setting::get('stripe_mode', '')));
        if (! in_array($currentMode, ['live', 'test'], true)) {
            Setting::set('stripe_mode', $legacyMode, 'text', 'payment');
        }
    }
}
