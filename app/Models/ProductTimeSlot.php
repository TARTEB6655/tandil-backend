<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin-configured booking slots for a single shop product.
 * When any rows exist for a product, shop booking uses these
 * instead of the global job_time_slots list.
 */
class ProductTimeSlot extends Model
{
    protected $table = 'product_time_slots';

    protected $fillable = [
        'product_id',
        'date',
        'start_time',
        'duration_minutes',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'date' => 'date',
        'duration_minutes' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function endTime(): string
    {
        $start = substr((string) $this->start_time, 0, 5);
        [$h, $m] = array_map('intval', explode(':', $start));
        $totalMinutes = ($h * 60 + $m + (int) $this->duration_minutes) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }

    public function startTimeHi(): string
    {
        return substr((string) $this->start_time, 0, 5);
    }

    public function appliesOnDate(string $date): bool
    {
        if ($this->date === null) {
            return true;
        }

        return Carbon::parse($this->date)->toDateString() === Carbon::parse($date)->toDateString();
    }
}
