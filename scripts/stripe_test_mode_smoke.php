<?php

/**
 * One-off: confirm a tiny test PaymentIntent using Stripe test card (no browser).
 * Requires STRIPE_SECRET_KEY=sk_test_... in .env. Refuses live keys.
 * Run from project root: php scripts/stripe_test_mode_smoke.php
 */
declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$secret = trim((string) config('services.stripe.secret'));
if ($secret === '') {
    fwrite(STDERR, "Missing STRIPE_SECRET_KEY in .env\n");
    exit(1);
}
if (! str_starts_with($secret, 'sk_test_')) {
    fwrite(STDERR, "Refusing: only sk_test_* keys allowed (live keys blocked).\n");
    exit(1);
}

$currency = strtolower((string) config('shop.currency', 'aed'));
// Stripe minimum ~USD 0.50 equivalent; AED uses minor units (fils): 200 = 2.00 AED
$amountMinor = 200;

$resp = Illuminate\Support\Facades\Http::withToken($secret)
    ->asForm()
    ->post('https://api.stripe.com/v1/payment_intents', [
        'amount' => $amountMinor,
        'currency' => $currency,
        'confirm' => 'true',
        'description' => 'Tandil backend smoke test (delete in Dashboard if you want)',
        'automatic_payment_methods[enabled]' => 'true',
        'automatic_payment_methods[allow_redirects]' => 'never',
        'payment_method_data[type]' => 'card',
        'payment_method_data[card][token]' => 'tok_visa',
    ]);

if (! $resp->successful()) {
    $msg = $resp->json('error.message') ?? $resp->body();
    fwrite(STDERR, 'Stripe API error: '.(is_string($msg) ? $msg : json_encode($msg))."\n");
    exit(1);
}

$data = $resp->json();
$status = $data['status'] ?? 'unknown';
$id = $data['id'] ?? 'n/a';

echo "Stripe test mode OK.\n";
echo "PaymentIntent: {$id}\n";
echo "status: {$status}\n";
echo "Open: https://dashboard.stripe.com/test/payments (Test mode toggle on)\n";

exit($status === 'succeeded' ? 0 : 2);
