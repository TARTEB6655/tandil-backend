<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\AdminNotification;
use Carbon\CarbonInterface;

/**
 * In-app inbox notifications for shop wallet activity (English copy only).
 */
final class ClientWalletNotificationService
{
    public static function notifyRefundCredited(
        int $userId,
        float $amount,
        int $orderId,
        string $orderNumber,
        float $newBalance,
        ?CarbonInterface $expiresAt
    ): void {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $amt = number_format($amount, 2);
        $bal = number_format($newBalance, 2);
        $expiryLine = $expiresAt
            ? ' This credit expires on '.$expiresAt->toDateString().' (store policy).'
            : '';

        $title = 'Wallet credit from refund';
        $message = "A refund of {$amt} AED was added to your wallet for order {$orderNumber}. Your wallet balance is now {$bal} AED.{$expiryLine}";

        $user->notify(new AdminNotification($title, $message, [
            'wallet_event' => 'refund_credited',
            'amount' => round($amount, 2),
            'currency' => 'AED',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'wallet_balance' => round($newBalance, 2),
            'expires_at' => $expiresAt?->toIso8601String(),
        ]));
    }

    public static function notifyWalletDebited(
        int $userId,
        float $amount,
        ?int $orderId,
        ?string $orderNumber,
        float $newBalance
    ): void {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $amt = number_format($amount, 2);
        $bal = number_format($newBalance, 2);
        $orderPart = $orderNumber !== null && $orderNumber !== ''
            ? " for order {$orderNumber}"
            : ($orderId !== null ? " for order #{$orderId}" : '');

        $title = 'Wallet balance used';
        $message = "{$amt} AED from your wallet was applied to your purchase{$orderPart}. Remaining wallet balance: {$bal} AED.";

        $user->notify(new AdminNotification($title, $message, [
            'wallet_event' => 'wallet_debited',
            'amount' => round($amount, 2),
            'currency' => 'AED',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'wallet_balance' => round($newBalance, 2),
        ]));
    }

    public static function notifyWalletForfeited(int $userId, float $expiredCreditAmount, float $newBalance): void
    {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $cred = number_format($expiredCreditAmount, 2);
        $bal = number_format($newBalance, 2);

        $title = 'Wallet credit expired';
        $message = "A wallet credit of {$cred} AED expired under store policy. Your wallet balance is now {$bal} AED.";

        $user->notify(new AdminNotification($title, $message, [
            'wallet_event' => 'wallet_forfeited',
            'credit_amount' => round($expiredCreditAmount, 2),
            'currency' => 'AED',
            'wallet_balance' => round($newBalance, 2),
        ]));
    }

    public static function notifyTopUpCredited(
        int $userId,
        float $amount,
        float $newBalance,
        ?string $paymentIntentId = null
    ): void {
        $user = User::query()->find($userId);
        if (! $user) {
            return;
        }

        $amt = number_format($amount, 2);
        $bal = number_format($newBalance, 2);

        $title = 'Wallet topped up';
        $message = "{$amt} AED was added to your wallet. Your available balance is now {$bal} AED.";

        $user->notify(new AdminNotification($title, $message, [
            'wallet_event' => 'top_up_credited',
            'amount' => round($amount, 2),
            'currency' => 'AED',
            'wallet_balance' => round($newBalance, 2),
            'payment_intent_id' => $paymentIntentId,
        ]));
    }
}
