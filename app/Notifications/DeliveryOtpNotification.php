<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app delivery OTP for product orders (no external SMS).
 */
class DeliveryOtpNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public VendorOrderMapping $mapping,
        public string $otp,
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
        $trackPath = '/orders/'.$order->id.'/track';
        $title = $this->isResend
            ? 'New delivery confirmation code'
            : 'Delivery confirmation code';
        $message = $this->isResend
            ? "A new OTP has been generated for order {$orderNumber}. To confirm delivery, share this code with the supplier when they arrive: {$this->otp}. Valid for {$this->ttlMinutes} minutes. You can also view it on Order Tracking in the Tandil app."
            : "Your order {$orderNumber} is out for delivery. To confirm delivery, share this OTP with the supplier when they arrive: {$this->otp}. Valid for {$this->ttlMinutes} minutes. Open Order Tracking in the Tandil app to view the code anytime.";

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $title,
            'message' => $message,
            'type' => 'delivery_otp',
            'delivery_channel' => 'in_app',
            'action_url' => $trackPath,
            'track_endpoint' => '/api/orders/'.$order->id.'/track',
            'order_id' => $order->id,
            'order_number' => $orderNumber,
            'otp' => $this->otp,
            'expires_at' => $this->mapping->delivery_otp_expires_at?->format('c'),
            'vendor_order_mapping_id' => $this->mapping->id,
            'meta' => [
                'entity' => 'shop_order',
                'type' => 'delivery_otp',
                'delivery_channel' => 'in_app',
                'order_id' => $order->id,
                'order_number' => $orderNumber,
                'vendor_order_mapping_id' => $this->mapping->id,
                'otp' => $this->otp,
                'expires_at' => $this->mapping->delivery_otp_expires_at?->format('c'),
                'action_url' => $trackPath,
                'track_endpoint' => '/api/orders/'.$order->id.'/track',
            ],
        ]);
    }
}
