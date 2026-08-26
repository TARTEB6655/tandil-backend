<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Vendor alert when a delivery OTP is issued/resent to the customer.
 * Does not include the OTP code. Single-use, no time expiry.
 */
class VendorDeliveryOtpIssuedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public VendorOrderMapping $mapping,
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
        $detailUrl = '/vendor/orders/'.$this->mapping->id;
        $title = $this->isResend
            ? 'New delivery OTP sent to customer'
            : 'Delivery OTP sent to customer';
        $message = $this->isResend
            ? "A new delivery OTP was sent in-app for order {$orderNumber}. Ask the customer for the code to confirm delivery. The code works only once."
            : "Delivery OTP was sent in-app for order {$orderNumber}. Ask the customer for the code when you arrive. The code works only once.";

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
            'single_use' => true,
            'expires' => false,
            'is_resend' => $this->isResend,
            'resend_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/resend-delivery-otp',
            'confirm_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/confirm-delivery',
            'meta' => [
                'entity' => 'vendor_order',
                'type' => 'vendor_delivery_otp_issued',
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'vendor_order_mapping_id' => $this->mapping->id,
                'single_use' => true,
                'expires' => false,
                'is_resend' => $this->isResend,
                'action_url' => $detailUrl,
                'resend_endpoint' => '/api/vendor/orders/'.$this->mapping->id.'/resend-delivery-otp',
            ],
        ]);
    }
}
