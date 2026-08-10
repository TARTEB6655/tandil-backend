<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Job Scheduling "Working hours & capacity" — singleton settings row.
 */
class JobSchedulingSetting extends Model
{
    protected $table = 'job_scheduling_settings';

    protected $fillable = [
        'working_hours',
        'max_bookings_per_slot',
        'max_bookings_per_day',
        'buffer_minutes',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'max_bookings_per_slot' => 'integer',
        'max_bookings_per_day' => 'integer',
        'buffer_minutes' => 'integer',
    ];

    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public static function defaultWorkingHours(): array
    {
        return collect(self::DAYS)->map(fn ($day) => [
            'day' => $day,
            'enabled' => true,
            'start' => '09:00',
            'end' => '18:00',
        ])->values()->toArray();
    }

    /** Get the singleton settings row, creating it with defaults if missing. */
    public static function current(): self
    {
        $row = self::query()->first();
        if ($row) {
            return $row;
        }

        return self::create([
            'working_hours' => self::defaultWorkingHours(),
            'max_bookings_per_slot' => 2,
            'max_bookings_per_day' => 12,
            'buffer_minutes' => 15,
        ]);
    }

    /** Working-hours config for one day (mon|tue|...), or null if not configured. */
    public function forDay(string $day): ?array
    {
        $day = strtolower(substr($day, 0, 3));
        foreach ((array) $this->working_hours as $row) {
            if (strtolower((string) ($row['day'] ?? '')) === $day) {
                return $row;
            }
        }

        return null;
    }
}
