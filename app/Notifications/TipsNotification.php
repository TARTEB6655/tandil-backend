<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TipsNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Home Care Tip')
            ->line('Here is a useful tip to keep your home in top shape this week.')
            ->action('Learn More', url('/'));
    }

    public function toArray($notifiable)
    {
        return ['message' => 'Weekly home-care tip'];
    }
}
