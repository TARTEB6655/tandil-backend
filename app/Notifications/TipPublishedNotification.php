<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to users (e.g. clients) when admin publishes a tip. Shows in unified Notifications list.
 */
class TipPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $content,
        public int $tipId
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->content,
            'type' => 'tip_published',
            'meta' => [
                'tip_id' => $this->tipId,
            ],
        ];
    }
}
