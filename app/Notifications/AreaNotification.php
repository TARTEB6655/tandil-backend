<?php

namespace App\Notifications;

use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AreaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $message
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return NotificationAudiencePayload::merge($notifiable, [
            'message' => $this->message,
            'type' => 'area_broadcast',
            'meta' => [
                'entity' => 'area',
                'source' => 'area_manager',
            ],
        ]);
    }
}
