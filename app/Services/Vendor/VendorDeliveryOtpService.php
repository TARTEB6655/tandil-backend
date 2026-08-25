<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Notifications\AdminNotification;
use App\Support\OrderFulfillmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product-order delivery OTP: customer shows code to vendor; vendor confirms completion.
 */
class VendorDeliveryOtpService
{
    public const OTP_TTL_HOURS = 72;

    public function ensureOtpForShipped(VendorOrderMapping $mapping): VendorOrderMapping
    {
        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            return $mapping;
        }

        if (
            filled($mapping->delivery_otp)
            && $mapping->delivery_otp_confirmed_at === null
            && ($mapping->delivery_otp_expires_at === null || $mapping->delivery_otp_expires_at->isFuture())
        ) {
            return $mapping;
        }

        $otp = (string) random_int(100000, 999999);
        $mapping->update([
            'delivery_otp' => $otp,
            'delivery_otp_expires_at' => now()->addHours(self::OTP_TTL_HOURS),
            'delivery_otp_confirmed_at' => null,
        ]);

        $this->notifyCustomerOtp($mapping->fresh(['order.user']) ?? $mapping, $otp);

        return $mapping->fresh() ?? $mapping;
    }

    public function confirmWithOtp(VendorOrderMapping $mapping, string $otp, User $vendorUser): VendorOrderMapping
    {
        if ($mapping->statusEnum() === VendorOrderStatus::Delivered
            && $mapping->delivery_otp_confirmed_at !== null) {
            return $mapping;
        }

        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            throw ValidationException::withMessages([
                'otp' => ['Order must be shipped before delivery OTP can be confirmed.'],
            ]);
        }

        $mapping = $this->ensureOtpForShipped($mapping);

        $expected = trim((string) $mapping->delivery_otp);
        $given = trim($otp);
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid delivery OTP.'],
            ]);
        }

        if ($mapping->delivery_otp_expires_at !== null && $mapping->delivery_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Delivery OTP has expired. Mark shipped again or ask the customer for a new code.'],
            ]);
        }

        return DB::transaction(function () use ($mapping, $vendorUser) {
            $mapping->update([
                'status' => VendorOrderStatus::Delivered->value,
                'delivery_otp_confirmed_at' => now(),
            ]);

            VendorOrderStatusLog::create([
                'vendor_order_mapping_id' => $mapping->id,
                'status' => VendorOrderStatus::Delivered->value,
                'changed_by' => $vendorUser->id,
                'note' => 'Delivery confirmed with customer OTP.',
            ]);

            $this->syncShopOrderStatus($mapping->fresh(['order']) ?? $mapping);

            return $mapping->fresh(['order.user', 'order.shippingAddress', 'statusLogs']) ?? $mapping;
        });
    }

    /**
     * Mirror vendor product fulfillment onto shop order_status for product-only orders.
     */
    public function syncShopOrderStatus(VendorOrderMapping $mapping): void
    {
        $order = $mapping->order ?? Order::query()->find($mapping->order_id);
        if (! $order instanceof Order || ! $order->isShopOrder()) {
            return;
        }

        if (! OrderFulfillmentType::usesVendorProductWorkflow($order)) {
            return;
        }

        $shopStatus = match ($mapping->statusEnum()) {
            VendorOrderStatus::Pending => 'processing',
            VendorOrderStatus::Confirmed => 'confirmed',
            VendorOrderStatus::Processing => 'in_progress',
            VendorOrderStatus::Shipped => 'completed',
            VendorOrderStatus::Delivered => 'delivered',
            VendorOrderStatus::Cancelled => 'cancelled',
        };

        if (strtolower((string) $order->order_status) === $shopStatus) {
            return;
        }

        $order->order_status = $shopStatus;
        $order->save();
    }

    /**
     * OTP visible to the customer (and admin) while awaiting vendor confirmation.
     */
    public function otpPayloadForCustomer(?VendorOrderMapping $mapping): ?array
    {
        if ($mapping === null) {
            return null;
        }

        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            return null;
        }

        if ($mapping->delivery_otp_confirmed_at !== null || ! filled($mapping->delivery_otp)) {
            return null;
        }

        return [
            'code' => (string) $mapping->delivery_otp,
            'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
            'instruction' => 'Give this OTP to the vendor when your order arrives to confirm delivery.',
        ];
    }

    private function notifyCustomerOtp(VendorOrderMapping $mapping, string $otp): void
    {
        $order = $mapping->order;
        $user = $order?->user;
        if (! $user instanceof User) {
            return;
        }

        try {
            $user->notify(new AdminNotification(
                'Delivery OTP for Order #'.$order->id,
                "Your delivery OTP is {$otp}. Share it with the vendor only when your order arrives.",
                [
                    'type' => 'delivery_otp',
                    'order_id' => $order->id,
                    'vendor_order_mapping_id' => $mapping->id,
                ]
            ));
        } catch (\Throwable) {
            // Non-fatal — OTP still available on track API.
        }
    }
}
