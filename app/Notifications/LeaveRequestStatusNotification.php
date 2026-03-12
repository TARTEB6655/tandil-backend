<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public LeaveRequest $leaveRequest,
        public string $status // 'approved' or 'rejected'
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        $isApproved = $this->status === 'approved';
        $title = $isApproved
            ? 'Leave request approved'
            : 'Leave request rejected';
        $dates = $this->leaveRequest->start_date->format('M d') . ' – ' . $this->leaveRequest->end_date->format('M d, Y');
        $message = $isApproved
            ? "Your leave ({$this->leaveRequest->leave_type}, {$dates}) has been approved by HR."
            : "Your leave request ({$this->leaveRequest->leave_type}, {$dates}) was not approved. Contact HR if you have questions.";

        return [
            'title' => $title,
            'message' => $message,
            'type' => 'leave_request_status',
            'meta' => [
                'leave_request_id' => $this->leaveRequest->id,
                'status' => $this->status,
                'leave_type' => $this->leaveRequest->leave_type,
                'start_date' => $this->leaveRequest->start_date->format('Y-m-d'),
                'end_date' => $this->leaveRequest->end_date->format('Y-m-d'),
            ],
        ];
    }
}
