<?php

namespace App\Services\Loyalty;

use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoyaltyService
{
    public const REFERENCE_ORDER = 'order';

    public const REFERENCE_SUBSCRIPTION = 'subscription';

    public static function isAutoEarnEnabled(): bool
    {
        return Setting::get('loyalty_auto_earn_enabled', '1') !== '0';
    }

    public static function pointsPerAed(): float
    {
        $rate = (float) Setting::get('loyalty_points_per_aed', '1');

        return max(0, $rate);
    }

    public function calculatePointsFromAmount(float $amount): int
    {
        if ($amount <= 0 || self::pointsPerAed() <= 0) {
            return 0;
        }

        return max(1, (int) floor($amount * self::pointsPerAed()));
    }

    public function hasEarnedForReference(string $referenceType, int $referenceId): bool
    {
        return LoyaltyTransaction::query()
            ->where('type', LoyaltyTransaction::TYPE_EARN)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();
    }

    public function creditForPaidOrder(Order $order): ?LoyaltyTransaction
    {
        if (! self::isAutoEarnEnabled()) {
            return null;
        }

        if ($order->user_id === null || strtolower((string) $order->payment_status) !== 'paid') {
            return null;
        }

        if ($this->hasEarnedForReference(self::REFERENCE_ORDER, (int) $order->id)) {
            return null;
        }

        $user = $order->relationLoaded('user') ? $order->user : User::query()->find($order->user_id);
        if (! $user || ! $this->userCanEarnLoyalty($user)) {
            return null;
        }

        $points = $this->calculatePointsFromAmount((float) $order->total_amount);
        if ($points <= 0) {
            return null;
        }

        return $this->awardPoints(
            $user,
            $points,
            'Order '.$order->publicOrderNumber().' completed',
            self::REFERENCE_ORDER,
            (int) $order->id
        );
    }

    public function creditForPaidSubscription(Subscription $subscription): ?LoyaltyTransaction
    {
        if (! self::isAutoEarnEnabled()) {
            return null;
        }

        if ($subscription->client_id === null || strtolower((string) $subscription->payment_status) !== 'paid') {
            return null;
        }

        if ($this->hasEarnedForReference(self::REFERENCE_SUBSCRIPTION, (int) $subscription->id)) {
            return null;
        }

        $user = $subscription->relationLoaded('client') ? $subscription->client : User::query()->find($subscription->client_id);
        if (! $user || ! $this->userCanEarnLoyalty($user)) {
            return null;
        }

        $points = $this->calculatePointsFromAmount((float) $subscription->amount);
        if ($points <= 0) {
            return null;
        }

        return $this->awardPoints(
            $user,
            $points,
            'Membership #'.$subscription->id.' completed',
            self::REFERENCE_SUBSCRIPTION,
            (int) $subscription->id
        );
    }

    private function userCanEarnLoyalty(User $user): bool
    {
        if (method_exists($user, 'isClient') && $user->isClient()) {
            return true;
        }

        return strtolower(trim((string) ($user->role ?? ''))) === 'client';
    }

    public function ensureDefaultRewards(): void
    {
        $defaults = [
            [
                'title' => 'Free Cleaning Service',
                'description' => 'Get a free basic cleaning service',
                'points_required' => 500,
                'sort_order' => 1,
            ],
            [
                'title' => 'Premium Polish',
                'description' => 'Premium polish treatment for your vehicle',
                'points_required' => 300,
                'sort_order' => 2,
            ],
            [
                'title' => 'Express Service',
                'description' => 'Priority express service booking',
                'points_required' => 100,
                'sort_order' => 3,
            ],
            [
                'title' => 'Waterproofing Treatment',
                'description' => 'Full waterproofing treatment package',
                'points_required' => 400,
                'sort_order' => 4,
            ],
        ];

        foreach ($defaults as $reward) {
            LoyaltyReward::query()->firstOrCreate(
                ['title' => $reward['title']],
                [
                    'description' => $reward['description'],
                    'points_required' => $reward['points_required'],
                    'sort_order' => $reward['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    public function getScreenPayload(User $user, int $transactionLimit = 20): array
    {
        $this->ensureDefaultRewards();

        $balance = (int) ($user->loyalty_points_balance ?? 0);

        return [
            'balance' => $balance,
            'available_rewards' => $this->availableRewards($balance),
            'recent_transactions' => $this->recentTransactions($user, $transactionLimit),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableRewards(int $balance): array
    {
        return LoyaltyReward::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LoyaltyReward $reward) => $this->formatReward($reward, $balance))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentTransactions(User $user, int $limit = 20): array
    {
        return LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (LoyaltyTransaction $transaction) => $this->formatTransaction($transaction))
            ->values()
            ->all();
    }

    public function redeemReward(User $user, int $rewardId): array
    {
        $this->ensureDefaultRewards();

        return DB::transaction(function () use ($user, $rewardId) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $reward = LoyaltyReward::query()
                ->whereKey($rewardId)
                ->where('is_active', true)
                ->first();

            if (! $reward) {
                throw new InvalidArgumentException('Reward not found or inactive.');
            }

            $balance = (int) ($lockedUser->loyalty_points_balance ?? 0);

            if ($balance < $reward->points_required) {
                throw new InvalidArgumentException('Not enough loyalty points to redeem this reward.');
            }

            $lockedUser->loyalty_points_balance = $balance - $reward->points_required;
            $lockedUser->save();

            LoyaltyTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'type' => LoyaltyTransaction::TYPE_REDEEM,
                'title' => 'Redeemed '.$reward->title,
                'points' => $reward->points_required,
                'loyalty_reward_id' => $reward->id,
                'transaction_date' => now()->toDateString(),
            ]);

            $lockedUser->refresh();

            return $this->getScreenPayload($lockedUser);
        });
    }

    public function awardPoints(
        User $user,
        int $points,
        string $title,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): LoyaltyTransaction {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to award must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $points, $title, $referenceType, $referenceId) {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $lockedUser->loyalty_points_balance = (int) ($lockedUser->loyalty_points_balance ?? 0) + $points;
            $lockedUser->save();

            return LoyaltyTransaction::query()->create([
                'user_id' => $lockedUser->id,
                'type' => LoyaltyTransaction::TYPE_EARN,
                'title' => $title,
                'points' => $points,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'transaction_date' => now()->toDateString(),
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function formatReward(LoyaltyReward $reward, int $balance): array
    {
        $canRedeem = $balance >= $reward->points_required;

        return [
            'id' => $reward->id,
            'title' => $reward->title,
            'description' => $reward->description,
            'points_required' => $reward->points_required,
            'can_redeem' => $canRedeem,
            'status' => $canRedeem ? LoyaltyReward::STATUS_AVAILABLE : LoyaltyReward::STATUS_NOT_ENOUGH_POINTS,
            'status_label' => $canRedeem ? 'Redeem' : 'Not Enough Points',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatTransaction(LoyaltyTransaction $transaction): array
    {
        $signedPoints = $transaction->type === LoyaltyTransaction::TYPE_REDEEM
            ? -1 * $transaction->points
            : $transaction->points;

        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'title' => $transaction->title,
            'date' => $transaction->transaction_date?->format('Y-m-d'),
            'points' => $transaction->points,
            'points_display' => ($signedPoints > 0 ? '+' : '').$signedPoints,
        ];
    }
}
