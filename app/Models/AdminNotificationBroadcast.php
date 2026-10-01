<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotificationBroadcast extends Model
{
    protected $fillable = [
        'sent_by_user_id',
        'title',
        'message',
        'scope_type',
        'scope_role',
        'messages_by_role',
        'recipient_client_count',
        'recipient_technician_count',
        'recipient_supervisor_count',
        'recipient_area_manager_count',
        'recipient_hr_count',
        'recipient_vendor_count',
        'recipient_admin_count',
        'recipient_other_count',
        'total_recipients',
    ];

    protected $casts = [
        'messages_by_role' => 'array',
    ];

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function recipientCountsForApi(): array
    {
        return [
            'customers' => $this->recipient_client_count,
            'technicians' => $this->recipient_technician_count,
            'supervisors' => $this->recipient_supervisor_count,
            'area_managers' => $this->recipient_area_manager_count,
            'hr' => $this->recipient_hr_count,
            'vendors' => $this->recipient_vendor_count,
            'admins' => $this->recipient_admin_count,
            'other' => $this->recipient_other_count,
            'total' => $this->total_recipients,
        ];
    }
}
