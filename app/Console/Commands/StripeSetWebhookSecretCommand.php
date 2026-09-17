<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\StripeCredentials;
use Illuminate\Console\Command;

/**
 * Store Stripe webhook signing secret in settings (preferred over committing .env).
 * Never prints the secret value.
 */
class StripeSetWebhookSecretCommand extends Command
{
    protected $signature = 'stripe:set-webhook-secret
                            {secret? : whsec_… signing secret (omit to read --from-env)}
                            {--mode=live : live or test}
                            {--from-env= : Env var name that holds the secret (e.g. STRIPE_LIVE_WEBHOOK_SECRET)}
                            {--set-mode : Also set stripe_mode to --mode}';

    protected $description = 'Configure Stripe webhook signing secret in DB settings (no secret echoed).';

    public function handle(): int
    {
        $mode = strtolower((string) $this->option('mode'));
        if (! in_array($mode, ['live', 'test'], true)) {
            $this->error('Mode must be live or test.');

            return self::FAILURE;
        }

        $secret = '';
        $arg = $this->argument('secret');
        if (is_string($arg) && trim($arg) !== '') {
            $secret = StripeCredentials::normalizeKey($arg);
        }

        $fromEnv = trim((string) $this->option('from-env'));
        if ($secret === '' && $fromEnv !== '') {
            $secret = StripeCredentials::normalizeKey((string) env($fromEnv, ''));
            if ($secret === '') {
                $this->error("Env {$fromEnv} is empty or not set.");

                return self::FAILURE;
            }
        }

        if ($secret === '') {
            $secret = StripeCredentials::normalizeKey((string) $this->secret('Paste webhook signing secret (whsec_…)'));
        }

        if ($secret === '' || ! str_starts_with($secret, 'whsec_')) {
            $this->error('Secret must start with whsec_.');

            return self::FAILURE;
        }

        $key = $mode === 'live' ? 'stripe_live_webhook_secret' : 'stripe_test_webhook_secret';
        Setting::set($key, $secret, 'text', 'payment');

        // Keep legacy fallback in sync for older code paths / cached envs.
        Setting::set('stripe_webhook_secret', $secret, 'text', 'payment');

        if ($this->option('set-mode')) {
            Setting::set('stripe_mode', $mode, 'text', 'payment');
        }

        Setting::set('stripe_keys_version', (string) ((int) Setting::get('stripe_keys_version', 0) + 1), 'number', 'payment');
        StripeCredentials::forgetCachedSettings();

        $this->info('Webhook signing secret stored.');
        $this->table(['Setting', 'Value'], [
            ['mode slot', $mode],
            ['setting key', $key],
            ['secret configured', 'YES'],
            ['secret prefix', substr($secret, 0, 8).'…'],
            ['stripe_mode (DB)', (string) Setting::get('stripe_mode', '(not set)')],
            ['active mode', StripeCredentials::activeMode()],
            ['webhook source', StripeCredentials::webhookSecretSource()],
            ['secrets available for verify', (string) count(StripeCredentials::webhookSecretsForVerification())],
        ]);

        $this->comment('If production uses config:cache, run: php artisan optimize:clear && php artisan config:cache');

        return self::SUCCESS;
    }
}
