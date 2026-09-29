<?php

namespace App\Notifications;

use App\Models\Vendor;
use App\Support\ContractorRegistrationNotifications;
use App\Support\NotificationAudiencePayload;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorApplicationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Vendor $vendor,
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
        $vendor = $this->vendor->loadMissing('profile');
        $copy = $this->copyForStatus();

        $message = $copy['message'];
        $messageAr = $copy['message_ar'];
        if ($this->status === 'rejected' && filled($this->reason)) {
            $message .= ' Reason: '.$this->reason;
            $messageAr .= ' السبب: '.$this->reason;
        }
        if ($this->status === 'missing_documents' && filled($this->notes)) {
            $message .= ' Admin comments: '.$this->notes;
            $messageAr .= ' ملاحظات المسؤول: '.$this->notes;
        }

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $copy['title'],
            'title_ar' => $copy['title_ar'],
            'message' => $message,
            'message_ar' => $messageAr,
            'type' => 'contractor_registration_'.$copy['event'],
            'meta' => [
                'entity' => 'contractor_registration',
                'event' => $copy['event'],
                'vendor_id' => $vendor->id,
                'status' => $this->status,
                'rejection_reason' => $this->reason,
                'notes' => $this->notes,
                'admin_review_message' => $vendor->profile?->admin_review_message,
                'business_name' => $vendor->profile?->business_name,
            ],
        ]);
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    private function copyForStatus(): array
    {
        return match ($this->status) {
            'approved' => ContractorRegistrationNotifications::approved(),
            'rejected' => ContractorRegistrationNotifications::rejected(),
            'missing_documents' => ContractorRegistrationNotifications::missingDocuments(),
            default => ContractorRegistrationNotifications::submitted(),
        };
    }
}
