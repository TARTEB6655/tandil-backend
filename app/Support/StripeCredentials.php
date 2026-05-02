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
     * Shop/API may use Stripe if a secret is configured and either admin enabled it
     * or the app runs in local/testing (so .env sandbox keys work without toggling admin).
     */
    public static function isStripeUsableForCheckout(): bool
    {
        if (self::secretKey() === '') {
            return false;
        }

        if (self::adminStripeEnabled()) {
            return true;
        }

        return app()->environment('local', 'testing');
    }
}
