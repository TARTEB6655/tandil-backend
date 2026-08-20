<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\VendorOrderMapping;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorNewPaidOrderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Order $order,
        public VendorOrderMapping $mapping
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $orderNumber = $this->order->publicOrderNumber();
        $amount = number_format((float) $this->mapping->total_amount, 2);

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => 'New paid order',
            'message' => "You have a new paid order {$orderNumber} for AED {$amount}. Open Orders to fulfill it.",
            'type' => 'vendor_new_paid_order',
            'meta' => [
                'entity' => 'vendor_order',
                'order_id' => $this->order->id,
                'order_number' => $orderNumber,
                'vendor_order_mapping_id' => $this->mapping->id,
                'vendor_id' => $this->mapping->vendor_id,
                'total_amount' => (float) $this->mapping->total_amount,
                'action_url' => '/vendor/orders/'.$this->mapping->id,
            ],
        ]);
    }
}
