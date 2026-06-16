<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\StripeCredentials;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class StripeGatewayStatusCommand extends Command
{
    protected $signature = 'stripe:status';

    protected $description = 'Show active Stripe checkout mode and key prefixes (no secrets).';

    public function handle(): int
    {
        StripeCredentials::forgetCachedSettings();

        $rows = Setting::query()
            ->whereIn('key', [
                'stripe_mode',
                'stripe_test_public_key',
                'stripe_live_public_key',
                'stripe_test_secret_key',
                'stripe_live_secret_key',
                'stripe_keys_version',
            ])
            ->pluck('value', 'key');

        $this->info('Stripe checkout status');
        $this->table(
            ['Setting', 'Value'],
            [
                ['stripe_mode (DB)', (string) ($rows['stripe_mode'] ?? '(not set)')],
                ['active checkout mode', StripeCredentials::activeMode()],
                ['keys_version', (string) StripeCredentials::keysVersion()],
                ['active publishable', StripeCredentials::maskedPublishablePrefix() ?? 'missing'],
                ['active secret', StripeCredentials::maskedSecretPrefix() ?? 'missing'],
                ['test publishable (DB)', $this->prefix((string) ($rows['stripe_test_public_key'] ?? ''))],
                ['test secret (DB)', $this->prefix((string) ($rows['stripe_test_secret_key'] ?? ''))],
                ['live publishable (DB)', $this->prefix((string) ($rows['stripe_live_public_key'] ?? ''))],
                ['live secret (DB)', $this->prefix((string) ($rows['stripe_live_secret_key'] ?? ''))],
                ['test mode ready', StripeCredentials::validateKeyPair(
                    StripeCredentials::keysForMode('test')['secret'],
                    StripeCredentials::keysForMode('test')['public']
                ) === [] ? 'yes' : 'no'],
                ['live mode ready', StripeCredentials::validateKeyPair(
                    StripeCredentials::keysForMode('live')['secret'],
                    StripeCredentials::keysForMode('live')['public']
                ) === [] ? 'yes' : 'no'],
            ]
        );

        foreach (StripeCredentials::blockingConfigurationIssues() as $issue) {
            $this->error('Issue: '.$issue);
        }

        Cache::forget('setting:stripe_mode');

        return self::SUCCESS;
    }

    private function prefix(string $key): string
    {
        $key = StripeCredentials::normalizeKey($key);

        return $key === '' ? '(empty)' : substr($key, 0, 12).'…';
    }
}
