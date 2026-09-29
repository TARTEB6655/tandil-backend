<?php

namespace App\Notifications;

use App\Models\SupervisorRegistration;
use App\Support\ContractorRegistrationNotifications;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SupervisorRegistrationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SupervisorRegistration $registration,
        public string $status,
        public ?string $reason = null,
        public ?string $notes = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $copy = match ($this->status) {
            'approved' => ContractorRegistrationNotifications::approved(),
            'rejected' => ContractorRegistrationNotifications::rejected(),
            'missing_documents', 'documents_requested' => ContractorRegistrationNotifications::missingDocuments(),
            default => ContractorRegistrationNotifications::submitted(),
        };

        $message = $copy['message'];
        $messageAr = $copy['message_ar'];
        if ($this->status === 'rejected' && filled($this->reason)) {
            $message .= ' Reason: '.$this->reason;
            $messageAr .= ' السبب: '.$this->reason;
        }
        if (in_array($this->status, ['missing_documents', 'documents_requested'], true) && filled($this->notes)) {
            $message .= ' Admin comments: '.$this->notes;
            $messageAr .= ' ملاحظات المسؤول: '.$this->notes;
        }

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $copy['title'],
            'title_ar' => $copy['title_ar'],
            'message' => $message,
            'message_ar' => $messageAr,
            'type' => 'supervisor_registration_'.$copy['event'],
            'meta' => [
                'entity' => 'supervisor_registration',
                'event' => $copy['event'],
                'registration_id' => $this->registration->id,
                'supervisor_id' => $this->registration->user_id,
                'status' => $this->status,
                'rejection_reason' => $this->reason,
                'notes' => $this->notes,
                'company_name' => $this->registration->company_name,
            ],
        ]);
    }
}
