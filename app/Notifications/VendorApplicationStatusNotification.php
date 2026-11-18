<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\Concerns\SendsFcmPush;
use App\Support\NotificationAudiencePayload;
use App\Support\VendorRegistrationNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorApplicationStatusNotification extends Notification
{
    use Queueable;
    use SendsFcmPush;

    public function __construct(
        public Vendor $vendor,
        public string $status,
        public ?string $reason = null,
        public ?string $notes = null
    ) {}

    /**
     * @return list<string|class-string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->channelsWithPush(['database']);
        if ($notifiable instanceof User && filled($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $copy = $this->resolvedCopy();

        return (new MailMessage)
            ->subject($copy['title'])
            ->line($copy['message']);
    }

    public function toArray(object $notifiable): array
    {
        $vendor = $this->vendor->loadMissing('profile');
        $copy = $this->resolvedCopy();

        $message = $copy['message'];
        $messageAr = $copy['message_ar'];
        if ($this->status === 'rejected' && filled($this->reason)) {
            $message .= ' Reason: '.$this->reason;
            $messageAr .= ' السبب: '.$this->reason;
        }
        if ($this->status === 'missing_documents' && filled($this->notes)) {
            $message .= ' '.$this->notes;
            $messageAr .= ' '.$this->notes;
        }

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $copy['title'],
            'title_ar' => $copy['title_ar'],
            'message' => $message,
            'message_ar' => $messageAr,
            'type' => 'vendor_registration_'.$copy['event'],
            'meta' => [
                'entity' => 'vendor_registration',
                'event' => $copy['event'],
                'vendor_id' => $vendor->id,
                'status' => $this->status,
                'rejection_reason' => $this->reason,
                'notes' => $this->notes,
                'admin_review_message' => $vendor->profile?->admin_review_message,
                'business_name' => $vendor->profile?->business_name,
                'can_resubmit' => $this->status === 'rejected',
            ],
        ]);
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    private function resolvedCopy(): array
    {
        return match ($this->status) {
            'registration_received' => VendorRegistrationNotifications::registrationReceived(),
            'registration_under_review', 'submitted' => VendorRegistrationNotifications::underReview(),
            'approved' => VendorRegistrationNotifications::approved(),
            'rejected' => VendorRegistrationNotifications::rejected(),
            'missing_documents' => VendorRegistrationNotifications::missingDocuments(),
            default => VendorRegistrationNotifications::underReview(),
        };
    }
}