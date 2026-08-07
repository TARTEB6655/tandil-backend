<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\Visit;
use App\Support\StripeCredentials;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Client "Membership checkout" — renew or upgrade a subscription via Stripe
 * Payment Sheet (card + Apple Pay), same pattern as WalletTopUpStripeService.
 * Uses metadata[purpose]=subscription_payment so webhooks never touch wallet
 * top-ups or shop orders.
 */
class SubscriptionPaymentStripeService
{
    public const PURPOSE = 'subscription_payment';

    public const ACTIONS = ['renew', 'upgrade'];

    private const PLAN_MONTHS = [
        '1_month' => 1,
        '3_month' => 3,
        '6_month' => 6,
        '12_month' => 12,
    ];

    /**
     * Full Membership screen in one call: the client's currently active membership
     * (with renew_price + upgrade_plans, same shape as upgradeOptions()) plus the
     * full "Your memberships" list — so the app never has to call List + Get
     * Upgrade Options separately just to render this screen.
     *
     * @return array{ok: true, data: array}
     */
    public function membershipScreen(User $user): array
    {
        $subs = Subscription::where('client_id', $user->id)
            ->with('visits')
            ->orderBy('created_at', 'desc')
            ->get();

        $today = Carbon::today();
        $active = $subs->first(function (Subscription $s) use ($today) {
            return $s->payment_status === 'paid'
                && $s->end_date
                && Carbon::parse($s->end_date)->gte($today);
        });

        $activeMembership = null;
        if ($active) {
            $options = $this->upgradeOptions($user, $active);
            if ($options['ok']) {
                $activeMembership = $options['data'];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'active_membership' => $activeMembership,
                'memberships' => $subs->values()->toArray(),
            ],
        ];
    }

    /**
     * Membership checkout "Upgrade plans" list: current plan + eligible upgrade targets.
     *
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    public function upgradeOptions(User $user, Subscription $subscription): array
    {
        if (! $this->ownsOrAdmin($user, $subscription)) {
            return $this->err('Forbidden.', 403);
        }

        $plans = (array) config('subscriptions.plans', []);
        $currentPrice = (float) ($plans[$subscription->plan]['price'] ?? $subscription->amount ?? 0);

        $targets = collect($plans)
            ->filter(fn ($cfg, $plan) => $plan !== $subscription->plan && (float) $cfg['price'] > $currentPrice)
            ->map(fn ($cfg, $plan) => [
                'plan' => $plan,
                'label' => $cfg['label'] ?? str_replace('_', ' ', $plan),
                'price' => round((float) $cfg['price'], 2),
                'price_difference' => round((float) $cfg['price'] - $currentPrice, 2),
            ])
            ->values();

        return [
            'ok' => true,
            'data' => [
                'currency' => 'AED',
                'current_membership' => [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan,
                    'plan_name' => $subscription->plan_name,
                    'amount' => round((float) $subscription->amount, 2),
                    'start_date' => optional($subscription->start_date)->toDateString(),
                    'end_date' => optional($subscription->end_date)->toDateString(),
                    'payment_status' => $subscription->payment_status,
                ],
                'renew_price' => $currentPrice,
                'upgrade_plans' => $targets,
                'payment_methods' => [
                    ['id' => 'stripe', 'label' => 'Stripe', 'description' => 'Pay securely with card via Stripe Payment Sheet'],
                    ['id' => 'apple_pay', 'label' => 'Apple Pay', 'description' => 'Fast and secure checkout with Apple Pay'],
                ],
            ],
        ];
    }

    /**
     * Create a PaymentIntent for renewing (same plan) or upgrading (target plan)
     * a membership. Supports card + Apple Pay via the same Payment Sheet.
     *
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    public function createPaymentIntent(Request $request, User $user, Subscription $subscription, string $action): array
    {
        if (! $this->ownsOrAdmin($user, $subscription)) {
            return $this->err('Forbidden.', 403);
        }

        if (! in_array($action, self::ACTIONS, true)) {
            return $this->err('Invalid action.', 422);
        }

        if (! StripeCredentials::isStripeUsableForCheckout()) {
            return $this->err('Stripe is not enabled or not configured.', 422);
        }

        $issues = StripeCredentials::blockingConfigurationIssues();
        if ($issues !== []) {
            return $this->err(implode(' ', $issues), 422);
        }

        $paymentMethod = strtolower(trim((string) $request->input('payment_method', 'stripe')));
        if (! in_array($paymentMethod, ['stripe', 'apple_pay'], true)) {
            return $this->err('payment_method must be stripe or apple_pay.', 422);
        }

        $plans = (array) config('subscriptions.plans', []);
        $fromPlan = $subscription->plan;

        if ($action === 'renew') {
            $toPlan = $fromPlan;
        } else {
            $toPlan = strtolower(trim((string) $request->input('target_plan', '')));
            if (! array_key_exists($toPlan, self::PLAN_MONTHS)) {
                return $this->err('target_plan must be one of: '.implode(', ', array_keys(self::PLAN_MONTHS)).'.', 422);
            }
            if ($toPlan === $fromPlan) {
                return $this->err('target_plan must be different from the current plan.', 422);
            }
        }

        $amount = round((float) ($plans[$toPlan]['price'] ?? 0), 2);
        if ($amount <= 0) {
            return $this->err('Could not resolve a price for this plan.', 422);
        }

        $currency = strtolower((string) config('shop.currency', 'aed'));
        $amountMinor = (int) round($amount * 100);

        $secret = StripeCredentials::secretKey();
        $ref = 'sp_'.Str::lower(Str::random(16));

        $payload = [
            'amount' => $amountMinor,
            'currency' => $currency,
            'automatic_payment_methods[enabled]' => 'true',
            'description' => Str::limit('Membership '.$action.' · '.($user->name ?? 'Client'), 999),
            'metadata[purpose]' => self::PURPOSE,
            'metadata[user_id]' => (string) $user->id,
            'metadata[subscription_id]' => (string) $subscription->id,
            'metadata[action]' => $action,
            'metadata[from_plan]' => $fromPlan,
            'metadata[to_plan]' => $toPlan,
            'metadata[ref]' => $ref,
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
            Log::warning('Membership payment PaymentIntent failed', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'action' => $action,
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

        // Auto-cancel stale pending rows for this subscription (>6h old)
        SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->whereNull('consumed_at')
            ->where('created_at', '<', now()->subHours(6))
            ->update(['status' => 'cancelled']);

        SubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'client_id' => $subscription->client_id,
            'action' => $action,
            'from_plan' => $fromPlan,
            'to_plan' => $toPlan,
            'amount' => $amount,
            'amount_minor' => $amountMinor,
            'currency' => $currency,
            'stripe_payment_intent_id' => $piId,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
            'meta' => ['ref' => $ref, 'purpose' => self::PURPOSE],
        ]);

        return [
            'ok' => true,
            'data' => [
                'client_secret' => $clientSecret,
                'payment_intent_id' => $piId,
                'publishable_key' => StripeCredentials::publishableKey(),
                'action' => $action,
                'from_plan' => $fromPlan,
                'to_plan' => $toPlan,
                'amount' => $amount,
                'currency' => strtoupper($currency),
                'payment_method' => $paymentMethod,
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
            return $this->err('This payment intent is not a membership payment. Use the matching confirm endpoint for that payment type.', 422);
        }

        $metaUserId = (int) ($pi['metadata']['user_id'] ?? 0);
        if ($metaUserId !== (int) $user->id) {
            return $this->err('Payment intent does not belong to this user.', 403);
        }

        if (($pi['status'] ?? '') !== 'succeeded') {
            return $this->err('Payment has not succeeded yet (status: '.($pi['status'] ?? 'unknown').').', 422);
        }

        $result = $this->applyPaymentIntent($pi, $user);
        if (! $result['ok']) {
            return $this->err($result['message'], $result['code']);
        }

        return [
            'ok' => true,
            'data' => $result['data'],
        ];
    }

    /**
     * Webhook entry: only handles PIs with metadata.purpose = subscription_payment.
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
            Log::warning('Membership payment webhook: user missing', ['pi' => $pi['id'] ?? null]);

            return;
        }

        $this->applyPaymentIntent($pi, $user);
    }

    /**
     * @return array{ok: true, data: array}|array{ok: false, message: string, code: int}
     */
    private function applyPaymentIntent(array $pi, User $user): array
    {
        $piId = (string) ($pi['id'] ?? '');
        if ($piId === '') {
            return $this->err('Missing payment intent id.', 422);
        }

        try {
            return DB::transaction(function () use ($pi, $piId, $user) {
                $payment = SubscriptionPayment::query()
                    ->where('stripe_payment_intent_id', $piId)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    return $this->err('Membership payment record not found for this payment intent.', 404);
                }

                if ((int) $payment->client_id !== (int) $user->id) {
                    return $this->err('Membership payment does not belong to this user.', 403);
                }

                if ($payment->isConsumed()) {
                    return [
                        'ok' => true,
                        'data' => $this->successPayload($payment, $payment->subscription()->first()),
                    ];
                }

                $subscription = Subscription::query()->whereKey($payment->subscription_id)->lockForUpdate()->first();
                if (! $subscription) {
                    return $this->err('Subscription not found.', 404);
                }

                $amount = round((float) $payment->amount, 2);
                if ($amount <= 0) {
                    return $this->err('Invalid payment amount.', 422);
                }

                if ($payment->action === 'renew') {
                    $this->applyRenew($subscription, $payment);
                } else {
                    $this->applyUpgrade($subscription, $payment);
                }

                $subscription->payment_status = 'paid';
                $subscription->payment_reference = $piId;
                $subscription->paid_at = now();
                $subscription->save();

                $payment->status = 'succeeded';
                $payment->consumed_at = now();
                $payment->save();

                try {
                    $subscription->client?->notify(new \App\Notifications\SubscriptionPaid($subscription));
                } catch (\Throwable $e) {
                    // ignore notify errors
                }

                return [
                    'ok' => true,
                    'data' => $this->successPayload($payment->fresh(), $subscription->fresh()),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('Membership payment apply failed', [
                'pi' => $piId,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->err('Failed to apply membership payment. If payment was taken, contact support.', 500);
        }
    }

    /** Extend the subscription by one more plan cycle and add fresh visits for that cycle. */
    private function applyRenew(Subscription $subscription, SubscriptionPayment $payment): void
    {
        $months = self::PLAN_MONTHS[$subscription->plan] ?? 1;
        $currentEnd = $subscription->end_date ? Carbon::parse($subscription->end_date) : Carbon::today();
        $newStart = $currentEnd->isFuture() ? $currentEnd->copy()->addDay() : Carbon::today();
        $newEnd = $newStart->copy()->addMonthsNoOverflow($months)->subDay();

        $subscription->end_date = $newEnd->toDateString();
        $subscription->amount = $payment->amount;

        $this->generateVisitsForRange($subscription, $newStart, $newEnd);
    }

    /** Switch the subscription to a new plan, restarting the cycle from today. */
    private function applyUpgrade(Subscription $subscription, SubscriptionPayment $payment): void
    {
        $months = self::PLAN_MONTHS[$payment->to_plan] ?? 1;
        $newStart = Carbon::today();
        $newEnd = $newStart->copy()->addMonthsNoOverflow($months)->subDay();

        $subscription->plan = $payment->to_plan;
        $subscription->start_date = $newStart->toDateString();
        $subscription->end_date = $newEnd->toDateString();
        $subscription->amount = $payment->amount;

        // Remove not-yet-completed visits from the old cycle; replace with a fresh schedule.
        Visit::where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->delete();

        $this->generateVisitsForRange($subscription, $newStart, $newEnd);
    }

    private function generateVisitsForRange(Subscription $subscription, Carbon $start, Carbon $end): void
    {
        $totalVisits = self::PLAN_MONTHS[$subscription->plan] ?? 1;
        $daysDiff = $start->diffInDays($end);
        $visitInterval = $totalVisits > 1 ? floor($daysDiff / ($totalVisits - 1)) : 0;

        for ($i = 0; $i < $totalVisits; $i++) {
            $scheduled = $i === 0
                ? $start->toDateString()
                : $start->copy()->addDays($visitInterval * $i)->toDateString();
            if (Carbon::parse($scheduled)->gt($end)) {
                $scheduled = $end->toDateString();
            }

            Visit::create([
                'subscription_id' => $subscription->id,
                'scheduled_date' => $scheduled,
                'status' => 'pending',
            ]);
        }
    }

    private function successPayload(SubscriptionPayment $payment, ?Subscription $subscription): array
    {
        return [
            'payment_intent_id' => $payment->stripe_payment_intent_id,
            'action' => $payment->action,
            'amount_paid' => round((float) $payment->amount, 2),
            'currency' => strtoupper((string) $payment->currency),
            'status' => $payment->status,
            'subscription' => $subscription,
        ];
    }

    private function ownsOrAdmin(User $user, Subscription $subscription): bool
    {
        return $user->hasRole('admin') || (int) $subscription->client_id === (int) $user->id;
    }

    /**
     * @return array{ok: false, message: string, code: int}
     */
    private function err(string $message, int $code): array
    {
        return ['ok' => false, 'message' => $message, 'code' => $code];
    }
}
