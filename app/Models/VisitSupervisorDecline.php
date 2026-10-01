<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Supervisor dismissed an unclaimed pool job (Reject on New Jobs).
 * Job stays available for other area supervisors.
 */
class VisitSupervisorDecline extends Model
{
    protected $table = 'visit_supervisor_declines';

    protected $fillable = [
        'visit_id',
        'supervisor_id',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
