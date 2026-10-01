<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitOffer extends Model
{
    protected $fillable = [
        'visit_id',
        'technician_id',
        'offered_at',
        'accept_by',
        'status',
        'reject_reason',
    ];

    protected $casts = [
        'offered_at' => 'datetime',
        'accept_by' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_TIMEOUT = 'timeout';

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
