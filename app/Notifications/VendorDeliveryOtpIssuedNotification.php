<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app alert for the vendor when a delivery OTP is issued or resent to the customer.
 * Does not include the OTP code (customer shares it verbally).
 */
class VendorDeliveryOtpIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public VendorOrderMapping $mapping,
        public int $ttlMinutes,
        public bool $isResend = false
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->order;
        $orderNumber = $order->publicOrderNumber();
        $expiresAt = $this->mapping->delivery_otp_expires_at;
        $expiresLabel = $expiresAt?->timezone(config('app.timezone'))->format('g:i A');
        $detailUrl = '/vendor/orders/'.$this->mapping->id;
        $title = $this->isResend
            ? 'New delivery OTP sent to customer'
            : 'Delivery OTP sent to customer';
        $message = $this->isResend
            ? "A new delivery OTP was sent in-app for order {$orderNumber}. It expires in {$this->ttlMinutes} minutes".($expiresLabel ? " (at {$expiresLabel})" : '').'. Ask the customer for the code, or tap Resend OTP if it expires.'
            : "Delivery OTP was sent in-app for order {$orderNumber}. Valid for {$this->ttlMinutes} minutes".($expiresLabel ? " (expires at {$expiresLabel})" : '').'. Ask the customer for the code when you arrive. If it expires, tap Resend OTP to create a new one.';

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $title,
            'message' => $message,
            'type' => 'vendor_delivery_otp_issued',
            'delivery_channel' => 'in_app',
            'action_url' => $detailUrl,
            'order_id' => $order->id,
            'order_number' => $orderNumber,
            'vendor_order_id' => $this->mapping->id,
            'vendor_order_mapping_id' => $this->mapping->id,
            'ttl_minutes' => $this->ttlMinutes,
            'expires_at' => $expiresAt?->format('c'),
            'expires_at_label' => $expiresLabel,
            'is_resend' => $this->isResend,
            'resend_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/resend-delivery-otp',
            'confirm_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/confirm-delivery',
            'meta' => [
                'entity' => 'vendor_order',
                'type' => 'vendor_delivery_otp_issued',
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'vendor_order_mapping_id' => $this->mapping->id,
                'ttl_minutes' => $this->ttlMinutes,
                'expires_at' => $expiresAt?->format('c'),
                'expires_at_label' => $expiresLabel,
                'is_resend' => $this->isResend,
                'action_url' => $detailUrl,
                'resend_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/resend-delivery-otp',
            ],
        ]);
    }
}
