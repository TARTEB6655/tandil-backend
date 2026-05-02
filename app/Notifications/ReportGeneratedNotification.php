<?php

namespace App\Notifications;

use App\Models\AdminReport;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AdminReport $report
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = url('/api/admin/reports/' . $this->report->id . '/download');
        return (new MailMessage)
            ->subject('Report ready: ' . $this->report->title)
            ->greeting('Hello ' . $notifiable->name)
            ->line('Your report "' . $this->report->title . '" has been generated successfully.')
            ->line('Type: ' . $this->report->type . ' | Generated at: ' . $this->report->generated_at->toDateTimeString())
            ->action('Download Report', $url)
            ->line('Thank you for using Tandil.');
    }

    public function toArray($notifiable): array
    {
        return NotificationAudiencePayload::merge($notifiable, [
            'type' => 'report_generated',
            'report_id' => $this->report->id,
            'title' => $this->report->title,
            'report_type' => $this->report->type,
            'generated_at' => $this->report->generated_at?->toIso8601String(),
        ]);
    }
}
