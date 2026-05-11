<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Support\RefundPolicy;
use Illuminate\Support\Facades\DB;

final class ShopOrderCancellationService
{
    public function isForbidden(?string $rawOrderStatus): bool
    {
        $s = strtolower(trim((string) ($rawOrderStatus ?? 'pending')));

        return in_array($s, ['delivered', 'cancelled'], true);
    }

    public function forbiddenMessage(?string $rawOrderStatus): string
    {
        return strtolower(trim((string) ($rawOrderStatus ?? ''))) === 'cancelled'
            ? 'This order is already cancelled.'
            : 'This order cannot be cancelled after delivery.';
    }

    /**
     * Apply RefundPolicy, credit wallet for logged-in customers, mark order cancelled.
     *
     * @return array{
     *   stage:string,
     *   refund_percent:float,
     *   refund_amount:float,
     *   service_fee_amount:float,
     *   wallet_credited:float,
     *   wallet_expires_at:?string
     * }
     */
    public function cancelOrder(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $decision = RefundPolicy::decisionForOrder($locked);
            $isPaid = strtolower((string) $locked->payment_status) === 'paid';
            $total = (float) $locked->total_amount;
            $refundAmount = $isPaid ? round($total * ((float) $decision['percent'] / 100), 2) : 0.0;
            $serviceFeeAmount = $isPaid ? round(max(0, $total - $refundAmount), 2) : 0.0;

            $walletCredited = 0.0;
            $expiresAt = null;
            if ($refundAmount > 0 && $locked->user_id) {
                $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->first();
                if ($user) {
                    $months = RefundPolicy::walletValidityMonths();
                    $expiresAt = now()->addMonths($months);
                    $walletCredited = $refundAmount;
                    $user->wallet_balance = round((float) ($user->wallet_balance ?? 0) + $walletCredited, 2);
                    $user->save();

                    WalletCredit::create([
                        'user_id' => $user->id,
                        'order_id' => $locked->id,
                        'amount' => $walletCredited,
                        'reason' => 'order_refund',
                        'status' => 'active',
                        'credited_at' => now(),
                        'expires_at' => $expiresAt,
                    ]);

                    $notifyUserId = (int) $user->id;
                    $notifyAmount = $walletCredited;
                    $notifyOrderId = (int) $locked->id;
                    $notifyOrderNumber = $locked->publicOrderNumber();
                    $notifyBalance = (float) $user->wallet_balance;
                    $notifyExpires = $expiresAt?->copy();
                    DB::afterCommit(function () use ($notifyUserId, $notifyAmount, $notifyOrderId, $notifyOrderNumber, $notifyBalance, $notifyExpires) {
                        ClientWalletNotificationService::notifyRefundCredited(
                            $notifyUserId,
                            $notifyAmount,
                            $notifyOrderId,
                            $notifyOrderNumber,
                            $notifyBalance,
                            $notifyExpires
                        );
                    });
                }
            }

            $locked->order_status = 'cancelled';
            $locked->payment_status = $isPaid ? 'refunded' : 'pending';
            $locked->refund_amount = $refundAmount;
            $locked->refund_reason = (string) ($decision['reason'] ?? 'Refund policy applied');
            $locked->refunded_at = $refundAmount > 0 ? now() : null;
            $locked->save();

            return [
                'stage' => (string) ($decision['stage'] ?? 'fallback'),
                'refund_percent' => (float) ($decision['percent'] ?? 0),
                'refund_amount' => $refundAmount,
                'service_fee_amount' => $serviceFeeAmount,
                'wallet_credited' => $walletCredited,
                'wallet_expires_at' => $expiresAt?->toIso8601String(),
            ];
        });
    }
}
