<?php

namespace App\Services\Vendor;

use App\Enums\VendorDisputeStatus;
use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Support\MarketplaceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminVendorOrderService
{
    public function updateStatus(VendorOrderMapping $mapping, VendorOrderStatus $status, User $admin, ?string $note = null): VendorOrderMapping
    {
        return DB::transaction(function () use ($mapping, $status, $admin, $note) {
            $mapping->update(['status' => $status->value]);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $status->value,
                'changed_by' => $admin->id,
                'note' => $note ?? 'Updated by admin.',
            ]);

            if ($status === VendorOrderStatus::Cancelled) {
                $mapping->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->id,
                ]);
            }

            return $mapping->fresh(['order.user', 'vendor.profile', 'statusLogs']);
        });
    }

    public function cancel(VendorOrderMapping $mapping, User $admin, string $reason): VendorOrderMapping
    {
        $mapping->update(['cancellation_reason' => $reason]);

        return $this->updateStatus($mapping, VendorOrderStatus::Cancelled, $admin, $reason);
    }

    public function updateDispute(VendorOrderMapping $mapping, VendorDisputeStatus $status, User $admin, ?string $notes = null): VendorOrderMapping
    {
        $mapping->update([
            'dispute_status' => $status->value,
            'dispute_notes' => $notes,
        ]);

        return $mapping->fresh(['order.user', 'vendor.profile']);
    }

    public function updatePaymentStatus(VendorOrderMapping $mapping, string $paymentStatus, User $admin): VendorOrderMapping
    {
        $order = $mapping->order;
        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order record not found.']);
        }

        $order->update(['payment_status' => $paymentStatus]);

        VendorOrderStatusLog::create([
            'vendor_order_mapping_id' => $mapping->id,
            'status' => $mapping->status,
            'changed_by' => $admin->id,
            'note' => 'Payment status updated to '.$paymentStatus.'.',
        ]);

        return $mapping->fresh(['order.user', 'vendor.profile']);
    }

    public function refund(VendorOrderMapping $mapping, User $admin, float $amount, ?string $reason = null): VendorOrderMapping
    {
        $order = $mapping->order;
        if (! $order) {
            throw ValidationException::withMessages(['order' => 'Order record not found.']);
        }

        if ($amount <= 0 || $amount > (float) $order->total_amount) {
            throw ValidationException::withMessages([
                'refund_amount' => 'Refund amount must be between 0.01 and the order total.',
            ]);
        }

        DB::transaction(function () use ($order, $mapping, $admin, $amount, $reason) {
            Transaction::create([
                'transaction_id' => 'REF-'.Str::upper(Str::random(12)),
                'transactionable_type' => Order::class,
                'transactionable_id' => $order->id,
                'type' => 'refund',
                'gateway' => $order->payment_method ?? 'manual',
                'amount' => $amount,
                'currency' => 'AED',
                'status' => 'completed',
                'notes' => $reason ?? 'Admin refund for vendor order #'.$mapping->order_id,
                'processed_at' => now(),
            ]);

            $order->update([
                'payment_status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $amount,
                'refund_reason' => $reason,
            ]);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => $mapping->status,
                'changed_by' => $admin->id,
                'note' => 'Refund processed: AED '.number_format($amount, 2),
            ]);
        });

        return $mapping->fresh(['order.user', 'vendor.profile']);
    }

    public function recalculateCommission(VendorOrderMapping $mapping): VendorOrderMapping
    {
        $rate = MarketplaceSettings::effectiveCommissionForVendor($mapping->vendor);
        $commission = round((float) $mapping->total_amount * ($rate / 100), 2);
        $mapping->update(['commission_amount' => $commission]);

        return $mapping;
    }
}
