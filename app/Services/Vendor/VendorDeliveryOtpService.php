<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Notifications\DeliveryOtpNotification;
use App\Support\OrderFulfillmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product-order delivery OTP: shown in the Tandil app to the customer when the vendor ships;
 * customer shares the code with the vendor to complete delivery. No external SMS.
 */
class VendorDeliveryOtpService
{
    public const OTP_TTL_MINUTES = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_MINUTES = 15;

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

        $wait = $this->resendCooldownRemainingSeconds($mapping);
        if ($wait > 0) {
            throw ValidationException::withMessages([
                'otp' => ["Please wait {$wait} seconds before requesting a new delivery OTP."],
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

        if ($mapping->delivery_otp_locked_until !== null && $mapping->delivery_otp_locked_until->isFuture()) {
            $seconds = max(1, (int) now()->diffInSeconds($mapping->delivery_otp_locked_until));
            throw ValidationException::withMessages([
                'otp' => ["Too many incorrect OTP attempts. Try again in {$seconds} seconds or resend a new OTP."],
            ]);
        }

        if (! filled($mapping->delivery_otp)) {
            throw ValidationException::withMessages([
                'otp' => ['No active delivery OTP. Use Resend OTP to generate a new code for the customer.'],
            ]);
        }

        if ($mapping->delivery_otp_expires_at !== null && $mapping->delivery_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Delivery OTP has expired. Use Resend OTP to generate a new code.'],
            ]);
        }

        $expected = trim((string) $mapping->delivery_otp);
        $given = trim($otp);
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            $this->registerFailedAttempt($mapping);

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
     * OTP visible to the customer on the track screen while awaiting vendor confirmation.
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

        if ($mapping->delivery_otp_expires_at !== null && $mapping->delivery_otp_expires_at->isPast()) {
            return [
                'otp' => null,
                'code' => null,
                'expired' => true,
                'delivery_channel' => 'in_app',
                'expires_at' => $mapping->delivery_otp_expires_at->format('c'),
                'instruction' => 'Your delivery OTP has expired. Ask the supplier to resend a new code in the app.',
            ];
        }

        $code = (string) $mapping->delivery_otp;

        return [
            'otp' => $code,
            'code' => $code,
            'expired' => false,
            'delivery_channel' => 'in_app',
            'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
            'sent_at' => $mapping->delivery_otp_sent_at?->format('c'),
            'ttl_minutes' => self::OTP_TTL_MINUTES,
            'instruction' => 'Share this code with the supplier when they arrive so they can confirm delivery in the app. No SMS is sent.',
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

        $cooldown = $this->resendCooldownRemainingSeconds($mapping);
        $lockedUntil = $mapping->delivery_otp_locked_until;
        $locked = $lockedUntil !== null && $lockedUntil->isFuture();
        $attempts = (int) ($mapping->delivery_otp_attempts ?? 0);

        return [
            'required' => true,
            'delivery_channel' => 'in_app',
            'has_active_otp' => $this->hasActiveOtp($mapping),
            'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
            'sent_at' => $mapping->delivery_otp_sent_at?->format('c'),
            'attempts' => $attempts,
            'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $attempts),
            'max_attempts' => self::MAX_ATTEMPTS,
            'locked' => $locked,
            'locked_until' => $locked ? $lockedUntil->format('c') : null,
            'resend_available_in_seconds' => $cooldown,
            'can_resend' => $cooldown === 0,
            'ttl_minutes' => self::OTP_TTL_MINUTES,
            'resend_cooldown_seconds' => self::RESEND_COOLDOWN_SECONDS,
            'instruction' => 'Customer sees the OTP in the Tandil app. Ask them to share it when the order arrives.',
        ];
    }

    public function resendCooldownRemainingSeconds(VendorOrderMapping $mapping): int
    {
        if ($mapping->delivery_otp_sent_at === null) {
            return 0;
        }

        $elapsed = $mapping->delivery_otp_sent_at->diffInSeconds(now());
        $remaining = self::RESEND_COOLDOWN_SECONDS - (int) $elapsed;

        return max(0, $remaining);
    }

    private function hasActiveOtp(VendorOrderMapping $mapping): bool
    {
        return filled($mapping->delivery_otp)
            && $mapping->delivery_otp_confirmed_at === null
            && ($mapping->delivery_otp_expires_at === null || $mapping->delivery_otp_expires_at->isFuture());
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
            'delivery_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'delivery_otp_confirmed_at' => null,
            'delivery_otp_sent_at' => now(),
            'delivery_otp_sent_to' => 'in_app',
            'delivery_otp_attempts' => 0,
            'delivery_otp_locked_until' => null,
        ]);

        $fresh = $mapping->fresh(['order.user', 'order.shippingAddress']) ?? $mapping;
        $this->notifyCustomerInApp((int) $fresh->order_id, $otp, $isResend);

        return $fresh;
    }

    private function registerFailedAttempt(VendorOrderMapping $mapping): void
    {
        $attempts = (int) $mapping->delivery_otp_attempts + 1;
        $updates = ['delivery_otp_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $updates['delivery_otp_locked_until'] = now()->addMinutes(self::LOCKOUT_MINUTES);
        }

        $mapping->update($updates);
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
                self::OTP_TTL_MINUTES,
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
