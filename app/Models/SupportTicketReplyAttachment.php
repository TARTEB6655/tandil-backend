<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketReplyAttachment extends Model
{
    protected $fillable = [
        'support_ticket_reply_id',
        'path',
        'original_name',
        'mime_type',
        'type',
        'size',
    ];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(SupportTicketReply::class, 'support_ticket_reply_id');
    }
}
