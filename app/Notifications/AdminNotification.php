<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;

class AdminNotification extends Notification
{
    use Queueable;

    protected $title;
    protected $message;
    protected $meta;

    public function __construct($title, $message, array $meta = [])
    {
        $this->title = $title;
        $this->message = $message;
        $this->meta = $meta;
    }

    public function via($notifiable)
    {
        // SMTP-free mode: keep notifications in database only.
        return ['database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject($this->title)
            ->line($this->message)
            ->action('View Dashboard', url('/'));
    }

    public function toArray($notifiable)
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'admin_notification',
            'meta' => $this->meta,
        ];
    }
}
