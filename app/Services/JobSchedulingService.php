<?php

namespace App\Services;

use App\Models\JobBlockedDate;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Visit;
use Carbon\Carbon;

/**
 * Job Scheduling: working hours/capacity, time slots, blocked dates, and
 * technician double-booking prevention — used by both the customer-facing
 * "available slots" lookup and the admin config/calendar endpoints.
 */
class JobSchedulingService
{
    private const NON_BLOCKING_STATUSES = ['rejected'];

    public static function settings(): JobSchedulingSetting
    {
        return JobSchedulingSetting::current();
    }

    private static function dayKey(string $date): string
    {
        return strtolower(Carbon::parse($date)->format('D'));
    }

    public static function isDateFullyBlocked(string $date): bool
    {
        return JobBlockedDate::whereDate('date', $date)
            ->where('block_type', JobBlockedDate::TYPE_FULL_DAY)
            ->exists();
    }

    public static function isSlotBlocked(string $date, string $time): bool
    {
        return JobBlockedDate::whereDate('date', $date)
            ->where('block_type', JobBlockedDate::TYPE_TIME_SLOT)
            ->where('time', $time)
            ->exists();
    }

    private static function slotDurationMinutes(string $time): int
    {
        $slot = JobTimeSlot::where('start_time', $time)->first();

        return $slot ? (int) $slot->duration_minutes : 60;
    }

    /**
     * Available time slots for a given date, with capacity + block info —
     * powers the customer "select date and available time slot" screen.
     */
    public static function availableSlots(string $date): array
    {
        $settings = self::settings();
        $dayCfg = $settings->forDay(self::dayKey($date));

        if (! $dayCfg || empty($dayCfg['enabled'])) {
            return [];
        }

        if (self::isDateFullyBlocked($date)) {
            return [];
        }

        $dayBookedCount = Visit::whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->count();
        $dayFull = $dayBookedCount >= $settings->max_bookings_per_day;

        return JobTimeSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get()
            ->filter(fn (JobTimeSlot $slot) => $slot->start_time >= $dayCfg['start'] && $slot->start_time < $dayCfg['end'])
            ->map(function (JobTimeSlot $slot) use ($date, $settings, $dayFull) {
                $blocked = self::isSlotBlocked($date, $slot->start_time);
                $booked = Visit::whereDate('scheduled_date', $date)
                    ->where('scheduled_time', $slot->start_time)
                    ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
                    ->count();
                $remaining = max(0, $settings->max_bookings_per_slot - $booked);

                return [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->endTime(),
                    'duration_minutes' => $slot->duration_minutes,
                    'booked_count' => $booked,
                    'remaining' => $remaining,
                    'available' => ! $blocked && ! $dayFull && $remaining > 0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Validate a (date, time) booking request. Returns an error message, or
     * null when it's bookable. When $time is null, no slot rules apply — this
     * keeps every existing scheduled_time-less visit flow unaffected.
     * $requireConfiguredSlot: false lets admin's Booking detail screen set a
     * custom Start/End range that isn't one of the customer-facing preset
     * time slots (working hours/blocked-date/capacity rules still apply).
     */
    public static function validateSlotForBooking(string $date, ?string $time, ?int $excludeVisitId = null, bool $requireConfiguredSlot = true): ?string
    {
        if ($time === null || $time === '') {
            return null;
        }

        $settings = self::settings();
        $dayCfg = $settings->forDay(self::dayKey($date));

        if (! $dayCfg || empty($dayCfg['enabled'])) {
            return 'Selected date is not a working day.';
        }

        if ($time < $dayCfg['start'] || $time >= $dayCfg['end']) {
            return 'Selected time is outside working hours.';
        }

        if (self::isDateFullyBlocked($date)) {
            return 'Selected date is blocked and not available for booking.';
        }

        if (self::isSlotBlocked($date, $time)) {
            return 'Selected time slot is blocked and not available for booking.';
        }

        if ($requireConfiguredSlot) {
            $slot = JobTimeSlot::where('start_time', $time)->where('is_active', true)->first();
            if (! $slot) {
                return 'Selected time is not a valid active time slot.';
            }
        }

        $bookedForSlot = Visit::whereDate('scheduled_date', $date)
            ->where('scheduled_time', $time)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when($excludeVisitId, fn ($q) => $q->where('id', '!=', $excludeVisitId))
            ->count();
        if ($bookedForSlot >= $settings->max_bookings_per_slot) {
            return 'This time slot is fully booked. Please choose another slot.';
        }

        $dayBooked = Visit::whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when($excludeVisitId, fn ($q) => $q->where('id', '!=', $excludeVisitId))
            ->count();
        if ($dayBooked >= $settings->max_bookings_per_day) {
            return 'This date is fully booked. Please choose another date.';
        }

        return null;
    }

    /**
     * True when the technician already has an overlapping (date, time) job —
     * time+duration+buffer taken into account. When $time is null (no slot
     * chosen), no conflict is reported, so date-only visits keep working.
     * $durationMinutes overrides the slot-lookup duration (used by the admin
     * Booking detail screen, which lets admin set a custom Start/End range).
     */
    public static function hasTechnicianConflict(int $technicianId, string $date, ?string $time, ?int $excludeVisitId = null, ?int $durationMinutes = null): bool
    {
        if ($time === null || $time === '') {
            return false;
        }

        $buffer = self::settings()->buffer_minutes;
        $duration = $durationMinutes ?? self::slotDurationMinutes($time);
        $newStart = Carbon::parse($date.' '.$time);
        $newEnd = $newStart->copy()->addMinutes($duration + $buffer);
        $newStartBuffered = $newStart->copy()->subMinutes($buffer);

        $existing = Visit::where('technician_id', $technicianId)
            ->whereDate('scheduled_date', $date)
            ->whereNotNull('scheduled_time')
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when($excludeVisitId, fn ($q) => $q->where('id', '!=', $excludeVisitId))
            ->get(['id', 'scheduled_date', 'scheduled_time', 'duration_minutes']);

        foreach ($existing as $visit) {
            $exStart = Carbon::parse($visit->scheduled_date->toDateString().' '.$visit->scheduled_time);
            $exDuration = $visit->duration_minutes ?? self::slotDurationMinutes($visit->scheduled_time);
            $exEnd = $exStart->copy()->addMinutes($exDuration);

            if ($newStartBuffered->lt($exEnd) && $newEnd->gt($exStart)) {
                return true;
            }
        }

        return false;
    }

    /** Minutes between two HH:mm strings (end must be after start). */
    public static function minutesBetween(string $startTime, string $endTime): int
    {
        return Carbon::parse($startTime)->diffInMinutes(Carbon::parse($endTime));
    }
}
