<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Job Scheduling "Time slots" — admin-configured slots customers can book from.
 */
class JobTimeSlot extends Model
{
    protected $table = 'job_time_slots';

    protected $fillable = [
        'start_time',
        'duration_minutes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function endTime(): string
    {
        [$h, $m] = array_map('intval', explode(':', $this->start_time));
        $totalMinutes = ($h * 60 + $m + (int) $this->duration_minutes) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }
}
