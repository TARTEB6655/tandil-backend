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
        $fromDb = trim((string) Setting::get('stripe_secret_key', ''));

        return $fromDb !== '' ? $fromDb : trim((string) config('services.stripe.secret', ''));
    }

    public static function publishableKey(): string
    {
        $fromDb = trim((string) Setting::get('stripe_public_key', ''));

        return $fromDb !== '' ? $fromDb : trim((string) config('services.stripe.key', ''));
    }

    public static function webhookSecret(): string
    {
        $fromDb = trim((string) Setting::get('stripe_webhook_secret', ''));

        return $fromDb !== '' ? $fromDb : trim((string) config('services.stripe.webhook_secret', ''));
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
}
