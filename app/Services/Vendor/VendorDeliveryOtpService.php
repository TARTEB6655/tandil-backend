<?php

namespace App\Services\Vendor;

use App\Enums\VendorOrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Models\VendorOrderMapping;
use App\Models\VendorOrderStatusLog;
use App\Notifications\AdminNotification;
use App\Services\Sms\OutboundSmsService;
use App\Support\OrderFulfillmentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Product-order delivery OTP: sent to the customer when the vendor ships / resends;
 * customer shares the code with the vendor to complete delivery.
 */
class VendorDeliveryOtpService
{
    public const OTP_TTL_MINUTES = 5;

    public const RESEND_COOLDOWN_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_MINUTES = 15;

    /** @deprecated Use OTP_TTL_MINUTES */
    public const OTP_TTL_HOURS = 0;

    public function __construct(
        private readonly OutboundSmsService $sms
    ) {}

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

        $mapping = $this->issueOtp($mapping, force: true);

        VendorOrderStatusLog::create([
            'vendor_order_mapping_id' => $mapping->id,
            'status' => VendorOrderStatus::Shipped->value,
            'changed_by' => $vendorUser->id,
            'note' => 'Delivery OTP resent to customer.',
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
                'otp' => ['No active delivery OTP. Use Resend OTP to send a new code to the customer.'],
            ]);
        }

        if ($mapping->delivery_otp_expires_at !== null && $mapping->delivery_otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['Delivery OTP has expired. Use Resend OTP to send a new code.'],
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
     * OTP visible to the customer (and admin) while awaiting vendor confirmation.
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
                'code' => null,
                'expired' => true,
                'expires_at' => $mapping->delivery_otp_expires_at->format('c'),
                'sent_to_phone_masked' => $this->maskPhone((string) ($mapping->delivery_otp_sent_to ?? '')),
                'instruction' => 'Your delivery OTP has expired. Ask the vendor to resend a new code.',
            ];
        }

        return [
            'code' => (string) $mapping->delivery_otp,
            'expired' => false,
            'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
            'sent_to_phone_masked' => $this->maskPhone((string) ($mapping->delivery_otp_sent_to ?? '')),
            'instruction' => 'Give this OTP to the vendor when your order arrives to confirm delivery.',
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
            'has_active_otp' => $this->hasActiveOtp($mapping),
            'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
            'sent_at' => $mapping->delivery_otp_sent_at?->format('c'),
            'sent_to_phone_masked' => $this->maskPhone((string) ($mapping->delivery_otp_sent_to ?? '')),
            'attempts' => $attempts,
            'attempts_remaining' => max(0, self::MAX_ATTEMPTS - $attempts),
            'max_attempts' => self::MAX_ATTEMPTS,
            'locked' => $locked,
            'locked_until' => $locked ? $lockedUntil->format('c') : null,
            'resend_available_in_seconds' => $cooldown,
            'can_resend' => $cooldown === 0,
            'ttl_minutes' => self::OTP_TTL_MINUTES,
            'resend_cooldown_seconds' => self::RESEND_COOLDOWN_SECONDS,
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

    private function issueOtp(VendorOrderMapping $mapping, bool $force = false): VendorOrderMapping
    {
        if (! $force && $this->hasActiveOtp($mapping)) {
            return $mapping;
        }

        $mapping = $mapping->relationLoaded('order')
            ? $mapping
            : ($mapping->fresh(['order.user', 'order.shippingAddress']) ?? $mapping);

        $phone = $this->resolveCustomerPhone($mapping);
        if ($phone === null || $phone === '') {
            throw ValidationException::withMessages([
                'otp' => ['Customer has no registered mobile number to receive the delivery OTP.'],
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $mapping->update([
            'delivery_otp' => $otp,
            'delivery_otp_expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
            'delivery_otp_confirmed_at' => null,
            'delivery_otp_sent_at' => now(),
            'delivery_otp_sent_to' => $phone,
            'delivery_otp_attempts' => 0,
            'delivery_otp_locked_until' => null,
        ]);

        $fresh = $mapping->fresh(['order.user', 'order.shippingAddress']) ?? $mapping;
        $this->notifyCustomerOtp($fresh, $otp, $phone);

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

    private function resolveCustomerPhone(VendorOrderMapping $mapping): ?string
    {
        $order = $mapping->order ?? Order::query()->with(['user', 'shippingAddress'])->find($mapping->order_id);
        if (! $order instanceof Order) {
            return null;
        }

        $candidates = [
            $order->payerPhone(),
            $order->guest_phone,
            $order->user?->phone,
            $order->shippingAddress?->phone_number,
        ];

        foreach ($candidates as $candidate) {
            $phone = trim((string) ($candidate ?? ''));
            if ($phone !== '') {
                return $phone;
            }
        }

        return null;
    }

    private function maskPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) <= 4) {
            return str_repeat('*', strlen($digits));
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }

    private function notifyCustomerOtp(VendorOrderMapping $mapping, string $otp, string $phone): void
    {
        $order = $mapping->order;
        $user = $order?->user;
        $message = "Your Tandil delivery OTP is {$otp}. It expires in ".self::OTP_TTL_MINUTES
            .' minutes. Share it with the vendor only when your order arrives.';

        try {
            $this->sms->send($phone, $message, [
                'type' => 'delivery_otp',
                'order_id' => $order?->id,
                'vendor_order_mapping_id' => $mapping->id,
            ]);
        } catch (\Throwable) {
            // Non-fatal — in-app notification / track API still expose the OTP.
        }

        if (! $user instanceof User) {
            return;
        }

        try {
            $user->notify(new AdminNotification(
                'Delivery OTP for Order #'.($order?->id ?? $mapping->order_id),
                $message,
                [
                    'type' => 'delivery_otp',
                    'order_id' => $order?->id,
                    'vendor_order_mapping_id' => $mapping->id,
                    'sent_to_phone_masked' => $this->maskPhone($phone),
                    'expires_at' => $mapping->delivery_otp_expires_at?->format('c'),
                ]
            ));
        } catch (\Throwable) {
            // Non-fatal — OTP still available on track API / SMS log.
        }
    }
}
