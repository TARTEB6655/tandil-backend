<?php

namespace App\Notifications;

use App\Models\Vendor;
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
        $businessName = $vendor->profile?->business_name ?? 'your business';

        $isApproved = $this->status === 'approved';

        $title = $isApproved
            ? 'Vendor application approved'
            : 'Vendor application rejected';

        $message = $isApproved
            ? "Congratulations! {$businessName} has been approved. You can now sign in and access your vendor dashboard."
            : "Your vendor application for {$businessName} was not approved."
                .($this->reason ? " Reason: {$this->reason}" : '')
                .' You may update your profile and resubmit.';

        return NotificationAudiencePayload::merge($notifiable, [
            'title' => $title,
            'message' => $message,
            'type' => 'vendor_application_status',
            'meta' => [
                'entity' => 'vendor_application',
                'vendor_id' => $vendor->id,
                'status' => $this->status,
                'rejection_reason' => $this->reason,
                'notes' => $this->notes,
                'business_name' => $vendor->profile?->business_name,
            ],
        ]);
    }
}
