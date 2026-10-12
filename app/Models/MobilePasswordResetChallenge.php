<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobilePasswordResetChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'phone_digits',
        'portal',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'send_count',
        'last_sent_at',
        'locked_until',
        'reset_token_hash',
        'reset_token_expires_at',
        'request_ip',
        'consumed_at',
    ];

    protected $casts = [
        'otp_expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'locked_until' => 'datetime',
        'reset_token_expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function isOtpExpired(): bool
    {
        return $this->otp_expires_at->isPast();
    }

    public function isResetTokenExpired(): bool
    {
        return $this->reset_token_expires_at === null || $this->reset_token_expires_at->isPast();
    }
}
