<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportChatMessage extends Model
{
    protected $fillable = [
        'support_chat_session_id',
        'user_id',
        'message',
        'attachment_path',
        'is_admin',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupportChatSession::class, 'support_chat_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (empty($this->attachment_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }
}
