<?php

namespace App\Notifications;

use App\Models\Report;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ReportFinalized extends Notification
{
    use Queueable;

    protected Report $report;

    public function __construct(Report $report)
    {
        $this->report = $report;
    }

    /**
     * Disabled system-wide: no in-app or email delivery (product decision).
     * Class kept so callers can stay type-safe; extend via() if this is re-enabled later.
     */
    public function via($notifiable)
    {
        return [];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your service report is ready')
            ->line('Your service report has been finalized by our supervisor.')
            ->action('View Report', url('/'))
            ->line('Thank you for using our service.');
    }

    public function toArray($notifiable)
    {
        return NotificationAudiencePayload::merge($notifiable, [
            'report_id' => $this->report->id,
            'visit_id' => $this->report->visit_id,
            'message' => 'Report finalized',
        ]);
    }
}
