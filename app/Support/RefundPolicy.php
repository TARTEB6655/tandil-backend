<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Setting;

class RefundPolicy
{
    public static function graceMinutes(): int
    {
        return max(0, (int) Setting::get('refund_grace_minutes', 15));
    }

    public static function partialRefundPercent(): float
    {
        $v = (float) Setting::get('refund_partial_percent', 50);

        return max(0, min(100, $v));
    }

    public static function serviceFeePercentAfterStart(): float
    {
        $v = (float) Setting::get('refund_service_fee_percent_after_start', 100);

        return max(0, min(100, $v));
    }

    public static function walletValidityMonths(): int
    {
        return max(1, (int) Setting::get('refund_wallet_validity_months', 6));
    }

    /**
     * @return array{stage:string,percent:float,reason:string}
     */
    public static function decisionForOrder(Order $order): array
    {
        $status = strtolower((string) ($order->order_status ?? 'pending'));
        $grace = self::graceMinutes();
        $partialPercent = self::partialRefundPercent();
        $serviceFeePercent = self::serviceFeePercentAfterStart();

        if ($order->created_at && now()->lessThanOrEqualTo($order->created_at->copy()->addMinutes($grace))) {
            return [
                'stage' => 'grace_window',
                'percent' => 100.0,
                'reason' => 'Cancelled within grace window.',
            ];
        }

        if (in_array($status, ['pending', 'paid'], true)) {
            return [
                'stage' => 'before_assignment',
                'percent' => 100.0,
                'reason' => 'Cancelled before assignment/processing.',
            ];
        }

        if (in_array($status, ['processing'], true)) {
            return [
                'stage' => 'assigned_not_started',
                'percent' => $partialPercent,
                'reason' => 'Order already assigned/processing.',
            ];
        }

        if (in_array($status, ['shipped', 'delivered'], true)) {
            $refundPercent = 100.0 - $serviceFeePercent;
            if ($refundPercent < 0) {
                $refundPercent = 0;
            }

            return [
                'stage' => 'service_started_or_completed',
                'percent' => $refundPercent,
                'reason' => 'Service already started/completed.',
            ];
        }

        return [
            'stage' => 'fallback',
            'percent' => 0.0,
            'reason' => 'No refund for current stage.',
        ];
    }

    public static function policyForApi(): array
    {
        $partial = self::partialRefundPercent();
        $serviceFee = self::serviceFeePercentAfterStart();
        $walletMonths = self::walletValidityMonths();

        return [
            'grace_minutes' => self::graceMinutes(),
            'wallet_validity_months' => $walletMonths,
            'rules' => [
                [
                    'stage' => 'before_assignment',
                    'label' => 'Before assignment/processing',
                    'refund_percent' => 100.0,
                ],
                [
                    'stage' => 'assigned_not_started',
                    'label' => 'Assigned but not started',
                    'refund_percent' => $partial,
                ],
                [
                    'stage' => 'service_started_or_completed',
                    'label' => 'Service started/completed',
                    'refund_percent' => max(0, 100.0 - $serviceFee),
                    'service_fee_percent' => $serviceFee,
                ],
            ],
            'wallet_terms' => [
                'credit_destination' => 'in_app_wallet',
                'expires_after_months' => $walletMonths,
            ],
        ];
    }
}
