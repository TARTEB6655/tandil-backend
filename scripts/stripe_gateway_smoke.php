<?php

/**
 * Smoke-check which Stripe mode a deployed backend exposes publicly.
 * Usage: php scripts/stripe_gateway_smoke.php [base_url]
 */
declare(strict_types=1);

$baseUrl = rtrim($argv[1] ?? (string) getenv('APP_URL') ?: 'http://localhost', '/');
$url = $baseUrl.'/api/shop/payment-gateways';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Accept: application/json'],
]);
$body = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) {
    fwrite(STDERR, "Failed to fetch {$url} (HTTP {$code})\n");
    exit(1);
}

$data = json_decode($body, true);
$stripe = null;
foreach (($data['data'] ?? []) as $gateway) {
    if (($gateway['type'] ?? '') === 'stripe') {
        $stripe = $gateway;
        break;
    }
}

if ($stripe === null) {
    fwrite(STDERR, "Stripe gateway not found in response.\n");
    exit(1);
}

$mode = (string) ($stripe['stripe_mode'] ?? 'unknown');
$pk = (string) ($stripe['publishable_key'] ?? '');
$pkPrefix = substr($pk, 0, 12);

echo "URL: {$url}\n";
echo "Stripe enabled: ".(($stripe['enabled'] ?? false) ? 'yes' : 'no')."\n";
echo "Checkout mode: {$mode}\n";
echo "Publishable key prefix: {$pkPrefix}…\n";
echo "Keys version: ".(string) ($stripe['keys_version'] ?? 'n/a')."\n";

if ($mode === 'live' && str_starts_with($pk, 'pk_test_')) {
    fwrite(STDERR, "MISMATCH: stripe_mode=live but publishable key is pk_test_\n");
    exit(2);
}
if ($mode === 'test' && str_starts_with($pk, 'pk_live_')) {
    fwrite(STDERR, "MISMATCH: stripe_mode=test but publishable key is pk_live_\n");
    exit(2);
}

echo "OK: mode and publishable key prefix align.\n";
exit(0);
