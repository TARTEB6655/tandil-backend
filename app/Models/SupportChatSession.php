<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SupportChatSession extends Model
{
    public const STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    protected $fillable = [
        'user_id',
        'token',
        'status',
        'subject',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportChatSession $session) {
            if (empty($session->token)) {
                $session->token = Str::lower(Str::random(48));
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportChatMessage::class)->orderBy('id');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'closed'], true);
    }
}
