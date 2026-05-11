<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies in-app wallet balance to a purchase by reducing credits (FIFO by expiry) and user.wallet_balance.
 */
final class ShopWalletRedemptionService
{
    /**
     * @throws \RuntimeException if balance is insufficient
     */
    public function redeem(User $user, float $amount, ?int $orderId = null, ?string $orderNumber = null): void
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        $userId = (int) $user->id;
        $notifyOrderId = $orderId;
        $notifyOrderNumber = $orderNumber;
        $notifyAmount = $amount;

        DB::transaction(function () use ($user, $amount, $userId, $notifyOrderId, $notifyOrderNumber, $notifyAmount) {
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $balance = (float) ($lockedUser->wallet_balance ?? 0);
            if ($balance + 0.00001 < $amount) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }

            if (Schema::hasTable('wallet_credits')) {
                $remaining = $amount;
                $credits = WalletCredit::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('status', 'active')
                    ->orderByRaw('case when expires_at is null then 1 else 0 end')
                    ->orderBy('expires_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($credits as $credit) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $cAmt = (float) $credit->amount;
                    if ($cAmt <= 0) {
                        continue;
                    }
                    if ($cAmt <= $remaining + 0.00001) {
                        $remaining = round($remaining - $cAmt, 2);
                        $credit->status = 'used';
                        $credit->save();
                    } else {
                        $credit->amount = round($cAmt - $remaining, 2);
                        $credit->save();
                        $remaining = 0;
                    }
                }

            }

            $newBalance = round($balance - $amount, 2);
            $lockedUser->wallet_balance = $newBalance;
            $lockedUser->save();

            DB::afterCommit(function () use ($userId, $notifyAmount, $notifyOrderId, $notifyOrderNumber, $newBalance) {
                ClientWalletNotificationService::notifyWalletDebited(
                    $userId,
                    $notifyAmount,
                    $notifyOrderId,
                    $notifyOrderNumber,
                    $newBalance
                );
            });
        });
    }

    /**
     * After a successful card/paypal payment, consume wallet portion once (idempotent via order.wallet_redeemed_at).
     */
    public function redeemAfterOrderPaid(\App\Models\Order $order): void
    {
        $applied = (float) ($order->wallet_amount_applied ?? 0);
        if ($applied <= 0 || ! $order->user_id || $order->wallet_redeemed_at) {
            return;
        }

        DB::transaction(function () use ($order, $applied) {
            $locked = \App\Models\Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->wallet_redeemed_at || (float) ($locked->wallet_amount_applied ?? 0) <= 0) {
                return;
            }
            $user = User::query()->whereKey($locked->user_id)->first();
            if (! $user) {
                return;
            }
            $this->redeem($user, (float) $locked->wallet_amount_applied, (int) $locked->id, $locked->publicOrderNumber());
            $locked->wallet_redeemed_at = now();
            $locked->save();
        });
    }
}
