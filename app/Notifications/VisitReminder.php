<?php

namespace App\Notifications;

use App\Models\Visit;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class VisitReminder extends Notification
{
    use Queueable;

    protected Visit $visit;

    public function __construct(Visit $visit)
    {
        $this->visit = $visit;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        $date = $this->visit->scheduled_date;
        return (new MailMessage)
            ->subject('Upcoming Visit Reminder')
            ->line("You have a scheduled visit on {$date}.")
            ->line('Please ensure someone is available at the address.')
            ->action('View Subscription', url('/'))
            ->line('Thank you for using our service!');
    }

    public function toArray($notifiable)
    {
        return [
            'visit_id' => $this->visit->id,
            'subscription_id' => $this->visit->subscription_id,
            'scheduled_date' => $this->visit->scheduled_date,
            'message' => 'Upcoming visit reminder',
        ];
    }
}
