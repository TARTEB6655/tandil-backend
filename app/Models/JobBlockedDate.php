<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Job Scheduling "Blocked dates & slots" — admin-closed dates/times.
 */
class JobBlockedDate extends Model
{
    protected $table = 'job_blocked_dates';

    public const TYPE_FULL_DAY = 'full_day';

    public const TYPE_TIME_SLOT = 'time_slot';

    protected $fillable = [
        'date',
        'block_type',
        'time',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
