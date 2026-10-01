<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-product blocked dates/slots (admin Job Scheduling → product).
 */
class ProductBlockedDate extends Model
{
    protected $table = 'product_blocked_dates';

    public const TYPE_FULL_DAY = 'full_day';

    public const TYPE_TIME_SLOT = 'time_slot';

    protected $fillable = [
        'product_id',
        'date',
        'block_type',
        'time',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function timeHi(): ?string
    {
        if ($this->time === null || $this->time === '') {
            return null;
        }

        return substr((string) $this->time, 0, 5);
    }

    public function isFullDay(): bool
    {
        return $this->block_type === self::TYPE_FULL_DAY;
    }

    public function matchesDate(string $date): bool
    {
        return Carbon::parse($this->date)->toDateString() === Carbon::parse($date)->toDateString();
    }
}
