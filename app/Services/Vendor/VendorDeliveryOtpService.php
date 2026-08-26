<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Notifications\DeliveryOtpNotification;
use App\Notifications\VendorDeliveryOtpIssuedNotification;
use App\Support\OrderFulfillmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product-order delivery OTP: shown in the Tandil app when the vendor ships.
 * Single-use only — no time expiry and no resend cooldown. Invalidated after confirm.
 */
class VendorDeliveryOtpService
{
    public function ensureOtpForShipped(VendorOrderMapping $mapping): VendorOrderMapping
    {
        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            return $mapping;
        }

        if ($this->hasActiveOtp($mapping)) {
            return $mapping;
        }

        return $this->issueOtp($mapping, force: true);
    }

    public function resendOtp(VendorOrderMapping $mapping, User $vendorUser): VendorOrderMapping
    {
        if ($mapping->statusEnum() === VendorOrderStatus::Delivered
            && $mapping->delivery_otp_confirmed_at !== null) {
            throw ValidationException::withMessages([
                'otp' => ['Delivery is already confirmed for this order.'],
            ]);
        }

        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            throw ValidationException::withMessages([
                'otp' => ['Order must be shipped before a delivery OTP can be resent.'],
            ]);
        }

        $mapping = $this->issueOtp($mapping, force: true, isResend: true);

        VendorOrderStatusLog::create([
            'vendor_order_mapping_id' => $mapping->id,
            'status' => VendorOrderStatus::Shipped->value,
            'changed_by' => $vendorUser->id,
            'note' => 'Delivery OTP resent to customer in app.',
        ]);

        return $mapping->fresh(['order.user', 'order.shippingAddress', 'statusLogs']) ?? $mapping;
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

        if (! filled($mapping->delivery_otp)) {
            throw ValidationException::withMessages([
                'otp' => ['No active delivery OTP. Use Resend OTP to generate a new code for the customer.'],
            ]);
        }

        $expected = trim((string) $mapping->delivery_otp);
        $given = trim($otp);
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid delivery OTP.'],
            ]);
        }

        return DB::transaction(function () use ($mapping, $vendorUser) {
            $mapping->update([
                'status' => VendorOrderStatus::Delivered->value,
                'delivery_otp' => null,
                'delivery_otp_expires_at' => null,
                'delivery_otp_confirmed_at' => now(),
                'delivery_otp_attempts' => 0,
                'delivery_otp_locked_until' => null,
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
            VendorOrderStatus::Pending => 'pending',
            VendorOrderStatus::Confirmed => 'confirmed',
            VendorOrderStatus::Processing => 'processing',
            VendorOrderStatus::Shipped => 'shipped',
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
     * OTP visible to the customer while awaiting vendor confirmation (single-use, no time expiry).
     *
     * @return array<string, mixed>|null
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

        $code = (string) $mapping->delivery_otp;

        return [
            'otp' => $code,
            'code' => $code,
            'single_use' => true,
            'expires' => false,
            'delivery_channel' => 'in_app',
            'sent_at' => $mapping->delivery_otp_sent_at?->format('c'),
            'instruction' => 'Share this code with the supplier when they arrive so they can confirm delivery. It can only be used once. No SMS is sent.',
        ];
    }

    /**
     * Vendor-facing OTP controls (never includes the OTP code).
     *
     * @return array<string, mixed>|null
     */
    public function otpControlsForVendor(VendorOrderMapping $mapping): ?array
    {
        if ($mapping->statusEnum() !== VendorOrderStatus::Shipped) {
            return null;
        }

        if ($mapping->delivery_otp_confirmed_at !== null) {
            return null;
        }

        return [
            'required' => true,
            'delivery_channel' => 'in_app',
            'has_active_otp' => $this->hasActiveOtp($mapping),
            'single_use' => true,
            'expires' => false,
            'sent_at' => $mapping->delivery_otp_sent_at?->format('c'),
            'can_resend' => true,
            'resend_endpoint' => '/api/vendor/orders/'.$mapping->id.'/resend-delivery-otp',
            'instruction' => 'Customer sees the OTP in the Tandil app. Ask them to share it when the order arrives. The code works once, then it is invalidated.',
        ];
    }

    /** @deprecated No cooldown — always 0. Kept for older callers. */
    public function resendCooldownRemainingSeconds(VendorOrderMapping $mapping): int
    {
        return 0;
    }

    private function hasActiveOtp(VendorOrderMapping $mapping): bool
    {
        return filled($mapping->delivery_otp)
            && $mapping->delivery_otp_confirmed_at === null;
    }

    private function issueOtp(VendorOrderMapping $mapping, bool $force = false, bool $isResend = false): VendorOrderMapping
    {
        if (! $force && $this->hasActiveOtp($mapping)) {
            return $mapping;
        }

        $mapping->loadMissing(['order.user', 'order.shippingAddress']);
        if ($mapping->order === null) {
            $mapping = $mapping->fresh(['order.user', 'order.shippingAddress']) ?? $mapping;
        }

        $otp = (string) random_int(100000, 999999);
        $mapping->update([
            'delivery_otp' => $otp,
            'delivery_otp_expires_at' => null,
            'delivery_otp_confirmed_at' => null,
            'delivery_otp_sent_at' => now(),
            'delivery_otp_sent_to' => 'in_app',
            'delivery_otp_attempts' => 0,
            'delivery_otp_locked_until' => null,
        ]);

        $fresh = $mapping->fresh(['order.user', 'order.shippingAddress', 'vendor.user']) ?? $mapping;
        $this->notifyCustomerInApp((int) $fresh->order_id, $otp, $isResend);
        $this->notifyVendorInApp($fresh, $isResend);

        return $fresh;
    }

    private function notifyVendorInApp(VendorOrderMapping $mapping, bool $isResend = false): void
    {
        $deliver = function () use ($mapping, $isResend): void {
            $fresh = VendorOrderMapping::query()
                ->with(['order', 'vendor.user'])
                ->find($mapping->id);

            if ($fresh === null || $fresh->order === null) {
                return;
            }

            $vendorUser = $fresh->vendor?->user;
            if (! $vendorUser instanceof User) {
                return;
            }

            $vendorUser->notify(new VendorDeliveryOtpIssuedNotification(
                $fresh->order,
                $fresh,
                $isResend
            ));
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($deliver);

            return;
        }

        $deliver();
    }

    private function notifyCustomerInApp(int $orderId, string $otp, bool $isResend = false): void
    {
        $deliver = function () use ($orderId, $otp, $isResend): void {
            $order = Order::query()->find($orderId);
            if (! $order instanceof Order || $order->user_id === null) {
                return;
            }

            $user = User::query()->find($order->user_id);
            if (! $user instanceof User) {
                return;
            }

            $mapping = VendorOrderMapping::query()
                ->where('order_id', $orderId)
                ->where('status', VendorOrderStatus::Shipped->value)
                ->latest('id')
                ->first();

            if ($mapping === null) {
                return;
            }

            $user->notify(new DeliveryOtpNotification(
                $order,
                $mapping,
                $otp,
                $isResend
            ));
        };

        if (DB::transactionLevel() > 0 && ! app()->runningUnitTests()) {
            DB::afterCommit($deliver);

            return;
        }

        $deliver();
    }
}
