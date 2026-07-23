<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletCredit;
use App\Models\WalletTopUp;
use App\Support\StripeCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Client wallet "Add Money" / top-up via Stripe Payment Sheet (card + Apple Pay).
 * Completely separate from shop cart/checkout and category/service payment flows.
 * Uses metadata[purpose]=wallet_topup so webhooks never create shop orders.
 */
class WalletTopUpStripeService
{
    public const PURPOSE = 'wallet_topup';

    /** Preset amounts shown on Add Money screen (AED). */
    public const PRESETS = [50, 100, 150, 200];

    /** Stripe AED minimum (~USD 0.50) — 2.00 AED in fils. */
    private const MIN_AMOUNT_MINOR = 200;

    /** Soft ceiling for custom top-ups (AED). */
    public const MAX_AMOUNT = 5000;

    /** Minimum custom top-up (AED) — aligns with Stripe min. */
    public const MIN_AMOUNT = 2;

    /**
     * Screen 1 data: balance, presets, limits, payment methods.
     *
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    public function options(User $user): array
    {
        $balance = round((float) ($user->wallet_balance ?? 0), 2);

        return [
            'ok' => true,
            'data' => [
                'currency' => 'AED',
                'available_balance' => $balance,
                'presets' => self::PRESETS,
                'min_amount' => self::MIN_AMOUNT,
                'max_amount' => self::MAX_AMOUNT,
                'payment_methods' => [
                    [
                        'id' => 'stripe',
                        'label' => 'Stripe',
                        'description' => 'Pay securely with card via Stripe Payment Sheet',
                    ],
                    [
                        'id' => 'apple_pay',
                        'label' => 'Apple Pay',
                        'description' => 'Fast and secure top-up with Apple Pay',
                    ],
                ],
            ],
        ];
    }

    /**
     * Create a PaymentIntent for wallet top-up (Payment Sheet — supports card + Apple Pay).
     *
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    public function createPaymentIntent(Request $request, User $user): array
    {
        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $issues = StripeCredentials::blockingConfigurationIssues();
        if ($issues !== []) {
            return $this->err(implode(' ', $issues), 422);
        }

        $amount = round((float) $request->input('amount', 0), 2);
        if ($amount < self::MIN_AMOUNT) {
            return $this->err('Minimum top-up amount is '.number_format(self::MIN_AMOUNT, 2).' AED.', 422);
        }
        if ($amount > self::MAX_AMOUNT) {
            return $this->err('Maximum top-up amount is '.number_format(self::MAX_AMOUNT, 2).' AED.', 422);
        }

        $paymentMethod = strtolower(trim((string) $request->input('payment_method', 'stripe')));
        if (! in_array($paymentMethod, ['stripe', 'apple_pay'], true)) {
            return $this->err('payment_method must be stripe or apple_pay.', 422);
        }

        $currency = strtolower((string) config('shop.currency', 'aed'));
        $amountMinor = (int) round($amount * 100);
        if ($currency === 'aed' && $amountMinor < self::MIN_AMOUNT_MINOR) {
            return $this->err('Amount is below the minimum for card payment (2.00 AED).', 422);
        }

        $balance = round((float) ($user->wallet_balance ?? 0), 2);
        $newBalancePreview = round($balance + $amount, 2);

        $secret = StripeCredentials::secretKey();
        $topUpRef = 'wt_'.Str::lower(Str::random(16));

        $payload = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => Str::limit('Wallet top-up · '.($user->name ?? 'Client'), 999),
            'metadata[purpose]' => self::PURPOSE,
            'metadata[user_id]' => (string) $user->id,
            'metadata[topup_ref]' => $topUpRef,
            'metadata[amount]' => (string) $amount,
            'metadata[currency]' => strtoupper($currency),
        ];

        if ($user->email) {
            $payload['receipt_email'] = $user->email;
        }

        $resp = Http::asForm()
            ->withToken($secret)
            ->timeout(30)
            ->post('https://api.stripe.com/v1/payment_intents', $payload);

        if (! $resp->successful()) {
            $msg = (string) ($resp->json('error.message') ?? 'Failed to create payment intent.');
            Log::warning('Wallet top-up PaymentIntent failed', [
                'user_id' => $user->id,
                'status' => $resp->status(),
                'error' => $resp->json('error'),
            ]);

            return $this->err($msg, 422);
        }

        $pi = $resp->json();
        $piId = (string) ($pi['id'] ?? '');
        $clientSecret = (string) ($pi['client_secret'] ?? '');
        if ($piId === '' || $clientSecret === '') {
            return $this->err('Stripe returned an incomplete payment intent.', 502);
        }

        WalletTopUp::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNull('consumed_at')
            ->where('created_at', '<', now()->subHours(6))
            ->update(['status' => 'cancelled']);

        WalletTopUp::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'stripe_payment_intent_id' => $piId,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'meta' => [
                'topup_ref' => $topUpRef,
                'purpose' => self::PURPOSE,
            ],
        ]);

        return [
            'ok' => true,
            'data' => [
                'client_secret' => $clientSecret,
                'payment_intent_id' => $piId,
                'publishable_key' => StripeCredentials::publishableKey(),
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'payment_method' => $paymentMethod,
                'available_balance' => $balance,
                'new_balance' => $newBalancePreview,
            ],
        ];
    }

    /**
     * Confirm after Payment Sheet succeeds (idempotent with webhook).
     *
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    public function confirm(Request $request, User $user): array
    {
        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $piId = trim((string) $request->input('payment_intent_id', ''));
        if ($piId === '' || ! str_starts_with($piId, 'pi_')) {
            return $this->err('payment_intent_id is required.', 422);
        }

        $secret = StripeCredentials::secretKey();
        $resp = Http::withToken($secret)->timeout(30)->get('https://api.stripe.com/v1/payment_intents/'.$piId);
        if (! $resp->successful()) {
            return $this->err((string) ($resp->json('error.message') ?? 'Could not verify payment.'), 422);
        }

        $pi = $resp->json();
        if (! is_array($pi)) {
            return $this->err('Invalid Stripe response.', 502);
        }

        $purpose = (string) ($pi['metadata']['purpose'] ?? '');
        if ($purpose !== self::PURPOSE) {
            return $this->err('This payment intent is not a wallet top-up. Use shop checkout confirm for product payments.', 422);
        }

        $metaUserId = (int) ($pi['metadata']['user_id'] ?? 0);
        if ($metaUserId !== (int) $user->id) {
            return $this->err('Payment intent does not belong to this user.', 403);
        }

        if (($pi['status'] ?? '') !== 'succeeded') {
            return $this->err('Payment has not succeeded yet (status: '.($pi['status'] ?? 'unknown').').', 422);
        }

        $result = $this->creditFromPaymentIntent($pi, $user);
        if (! $result['ok']) {
            return $this->err($result['message'], $result['code']);
        }

        return [
            'ok' => true,
            'data' => $result['data'],
        ];
    }

    /**
     * Webhook entry: only handles PIs with metadata.purpose = wallet_topup.
     */
    public function fulfillFromWebhookPaymentIntent(array $pi): void
    {
        $purpose = (string) ($pi['metadata']['purpose'] ?? '');
        if ($purpose !== self::PURPOSE) {
            return;
        }
        if (($pi['status'] ?? '') !== 'succeeded') {
            return;
        }

        $userId = (int) ($pi['metadata']['user_id'] ?? 0);
        $user = $userId > 0 ? User::query()->find($userId) : null;
        if (! $user) {
            Log::warning('Wallet top-up webhook: user missing', ['pi' => $pi['id'] ?? null]);

            return;
        }

        $this->creditFromPaymentIntent($pi, $user);
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    private function creditFromPaymentIntent(array $pi, User $user): array
    {
        $piId = (string) ($pi['id'] ?? '');
        if ($piId === '') {
            return $this->err('Missing payment intent id.', 422);
        }

        try {
            return DB::transaction(function () use ($pi, $piId, $user) {
                $topUp = WalletTopUp::query()
                    ->where('stripe_payment_intent_id', $piId)
                    ->lockForUpdate()
                    ->first();

                if (! $topUp) {
                    // Create from PI metadata if client confirm races ahead of local row (rare)
                    $amountMinor = (int) ($pi['amount_received'] ?? $pi['amount'] ?? 0);
                    $amount = round($amountMinor / 100, 2);
                    $topUp = WalletTopUp::create([
                        'user_id' => $user->id,
                        'amount' => $amount,
                        'amount_minor' => $amountMinor,
                        'currency' => strtolower((string) ($pi['currency'] ?? 'aed')),
                        'stripe_payment_intent_id' => $piId,
                        'status' => 'pending',
                        'meta' => ['recovered_from_pi' => true, 'purpose' => self::PURPOSE],
                    ]);
                    $topUp = WalletTopUp::query()->whereKey($topUp->id)->lockForUpdate()->first();
                }

                if ((int) $topUp->user_id !== (int) $user->id) {
                    return $this->err('Wallet top-up does not belong to this user.', 403);
                }

                if ($topUp->isConsumed()) {
                    $fresh = $user->fresh();

                    return [
                        'ok' => true,
                        'data' => $this->successPayload($topUp, $fresh),
                    ];
                }

                $amount = round((float) $topUp->amount, 2);
                if ($amount <= 0) {
                    return $this->err('Invalid top-up amount.', 422);
                }

                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->first();
                $lockedUser->wallet_balance = round((float) ($lockedUser->wallet_balance ?? 0) + $amount, 2);
                $lockedUser->save();

                // Top-up credits do not expire (user paid real money). Refund credits keep their own policy.
                $credit = WalletCredit::create([
                    'user_id' => $lockedUser->id,
                    'order_id' => null,
                    'amount' => $amount,
                    'reason' => 'top_up',
                    'status' => 'active',
                    'credited_at' => now(),
                    'expires_at' => null,
                ]);

                $topUp->status = 'succeeded';
                $topUp->consumed_at = now();
                $topUp->wallet_credit_id = $credit->id;
                $topUp->save();

                $balance = (float) $lockedUser->wallet_balance;
                $notifyUserId = (int) $lockedUser->id;
                $notifyAmount = $amount;
                DB::afterCommit(function () use ($notifyUserId, $notifyAmount, $balance, $piId) {
                    ClientWalletNotificationService::notifyTopUpCredited(
                        $notifyUserId,
                        $notifyAmount,
                        $balance,
                        $piId
                    );
                });

                return [
                    'ok' => true,
                    'data' => $this->successPayload($topUp->fresh(), $lockedUser),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Wallet top-up credit failed', [
                'pi' => $piId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->err('Failed to credit wallet. If payment was taken, contact support.', 500);
        }
    }

    private function successPayload(WalletTopUp $topUp, User $user): array
    {
        return [
            'payment_intent_id' => $topUp->stripe_payment_intent_id,
            'amount_added' => round((float) $topUp->amount, 2),
            'currency' => strtoupper((string) $topUp->currency),
            'available_balance' => round((float) ($user->wallet_balance ?? 0), 2),
            'status' => $topUp->status,
        ];
    }

    /**
     * @return array{ok: false, message: string, code: int}
     */
    private function err(string $message, int $code): array
    {
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }
}
