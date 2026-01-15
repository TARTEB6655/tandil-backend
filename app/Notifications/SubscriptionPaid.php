<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Subscription;

class SubscriptionPaid extends Notification
{
    use Queueable;

    protected Subscription $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
                    ->subject('Subscription Payment Confirmed')
                    ->greeting('Hello '.$notifiable->name)
                    ->line('Your subscription payment has been confirmed.')
                    ->line('Plan: '.$this->subscription->plan)
                    ->line('Amount: AED '.number_format($this->subscription->amount ?? 0, 2))
                    ->line('Payment Status: '.ucfirst($this->subscription->payment_status))
                    ->action('View Subscription', url('/'))
                    ->line('Thank you for your payment!');
    }

    public function toArray($notifiable)
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan' => $this->subscription->plan,
            'amount' => $this->subscription->amount,
            'payment_status' => $this->subscription->payment_status,
            'message' => 'Your subscription payment has been confirmed.',
        ];
    }
}

