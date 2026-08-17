<?php

namespace App\Services;

use App\Models\JobBlockedDate;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Visit;
use Carbon\Carbon;

/**
 * Job Scheduling:
 * - Working days
 * - Working hours
 * - Time slots
 * - Blocked dates / slots
 * - Daily and per-slot capacity
 * - Technician double-booking prevention
 *
 * IMPORTANT:
 * Time slots are shown to customers only when the complete
 * slot falls within the working hours of the selected day.
 */
class JobSchedulingService
{
    private const NON_BLOCKING_STATUSES = ['rejected'];

    /**
     * Get current scheduling settings.
     */
    public static function settings(): JobSchedulingSetting
    {
        return JobSchedulingSetting::current();
    }

    /**
     * Get day key from date.
     *
     * Example:
     * 2026-08-13 => thu
     */
    private static function dayKey(string $date): string
    {
        return strtolower(Carbon::parse($date)->format('D'));
    }

    /**
     * Check whether the complete date is blocked.
     */
    public static function isDateFullyBlocked(string $date): bool
    {
        return JobBlockedDate::whereDate('date', $date)
            ->where('block_type', JobBlockedDate::TYPE_FULL_DAY)
            ->exists();
    }

    /**
     * Check whether a particular time slot is blocked on a date.
     */
    public static function isSlotBlocked(string $date, string $time): bool
    {
        return JobBlockedDate::whereDate('date', $date)
            ->where('block_type', JobBlockedDate::TYPE_TIME_SLOT)
            ->where('time', $time)
            ->exists();
    }

    /**
     * Get all blocked dates / slots for a specific date.
     *
     * This is used by the available-slots API so the frontend
     * can know whether the date is fully blocked or only
     * specific time slots are blocked.
     *
     * Returns examples:
     *
     * Full day:
     * {
     *     "id": 1,
     *     "block_type": "full_day",
     *     "date": "2026-08-18",
     *     "time": null,
     *     "reason": "Holiday"
     * }
     *
     * Time slot:
     * {
     *     "id": 2,
     *     "block_type": "time_slot",
     *     "date": "2026-08-19",
     *     "time": "10:00",
     *     "reason": "Meeting"
     * }
     */
    public static function blockedSlots(string $date): array
    {
        return JobBlockedDate::whereDate('date', $date)
            ->orderByRaw('time IS NULL DESC')
            ->orderBy('time')
            ->get()
            ->map(function (JobBlockedDate $block) {
                return [
                    'id' => $block->id,
                    'block_type' => $block->block_type,
                    'date' => Carbon::parse($block->date)->toDateString(),
                    'time' => $block->time
                        ? substr((string) $block->time, 0, 5)
                        : null,
                    'reason' => $block->reason ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Get duration of a configured time slot.
     *
     * Falls back to 60 minutes if the slot cannot be found.
     */
    private static function slotDurationMinutes(string $time): int
    {
        $slot = JobTimeSlot::where('start_time', $time)->first();

        return $slot
            ? (int) $slot->duration_minutes
            : 60;
    }

    /**
     * Get available time slots for a given date.
     *
     * A slot is returned only when:
     *
     * - The selected day is enabled
     * - The date is not fully blocked
     * - The slot is active
     * - The complete slot falls within working hours
     * - The slot itself is not blocked
     * - Slot capacity is available
     * - Daily capacity is available
     */
    public static function availableSlots(string $date): array
    {
        $settings = self::settings();

        /**
         * Get working-day configuration.
         *
         * Example:
         *
         * Monday:
         * enabled = true
         * start   = 09:00
         * end     = 18:00
         */
        $dayCfg = $settings->forDay(self::dayKey($date));

        /**
         * If the day is disabled / not configured,
         * there are no available slots.
         */
        if (! $dayCfg || empty($dayCfg['enabled'])) {
            return [];
        }

        /**
         * Entire date is blocked.
         */
        if (self::isDateFullyBlocked($date)) {
            return [];
        }

        /**
         * Check total bookings for the date.
         */
        $dayBookedCount = Visit::whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->count();

        $dayFull = $dayBookedCount >= $settings->max_bookings_per_day;

        /**
         * Get all active configured slots first.
         *
         * We cannot use:
         *
         * ->whereTime('end_time', ...)
         *
         * because end_time is calculated by JobTimeSlot::endTime()
         * and is not necessarily a database column.
         */
        $slots = JobTimeSlot::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get()
            ->filter(function (JobTimeSlot $slot) use ($dayCfg) {

                /**
                 * Slot start time.
                 */
                $slotStart = Carbon::createFromFormat(
                    'H:i',
                    substr((string) $slot->start_time, 0, 5)
                );

                /**
                 * Slot end time is calculated by the model.
                 */
                $slotEnd = Carbon::createFromFormat(
                    'H:i',
                    substr((string) $slot->endTime(), 0, 5)
                );

                /**
                 * Working hours start.
                 */
                $workingStart = Carbon::createFromFormat(
                    'H:i',
                    substr((string) $dayCfg['start'], 0, 5)
                );

                /**
                 * Working hours end.
                 */
                $workingEnd = Carbon::createFromFormat(
                    'H:i',
                    substr((string) $dayCfg['end'], 0, 5)
                );

                /**
                 * The COMPLETE slot must be inside working hours.
                 *
                 * Example:
                 *
                 * Working hours: 09:00 - 18:00
                 *
                 * 06:30 - 07:30 => false
                 * 08:30 - 09:30 => false
                 * 09:00 - 10:00 => true
                 * 17:00 - 18:00 => true
                 * 17:30 - 18:30 => false
                 * 18:00 - 19:00 => false
                 */
                return $slotStart->greaterThanOrEqualTo($workingStart)
                    && $slotEnd->lessThanOrEqualTo($workingEnd);
            })
            ->values();

        /**
         * Now calculate availability for every valid slot.
         */
        return $slots
            ->map(function (JobTimeSlot $slot) use (
                $date,
                $settings,
                $dayFull
            ) {

                /**
                 * Check whether this exact slot is blocked
                 * for this date.
                 */
                $blocked = self::isSlotBlocked(
                    $date,
                    $slot->start_time
                );

                /**
                 * Count bookings for this exact date + time.
                 */
                $booked = Visit::whereDate('scheduled_date', $date)
                    ->where('scheduled_time', $slot->start_time)
                    ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
                    ->count();

                /**
                 * Calculate remaining capacity.
                 */
                $remaining = max(
                    0,
                    $settings->max_bookings_per_slot - $booked
                );

                return [
                    'id' => $slot->id,
                    'start_time' => $slot->start_time,
                    'end_time' => $slot->endTime(),
                    'duration_minutes' => (int) $slot->duration_minutes,
                    'booked_count' => $booked,
                    'remaining' => $remaining,

                    'blocked' => $blocked,

                    'available' => ! $blocked
                        && ! $dayFull
                        && $remaining > 0,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Validate a date/time booking request.
     *
     * The selected date must:
     * - Be a working day
     * - Not be fully blocked
     *
     * When a configured slot is required, the selected time must:
     * - Belong to an active configured slot
     * - Fall completely within working hours
     * - Not be blocked
     * - Have available slot capacity
     * - Have available daily capacity
     *
     * When $time is null, no slot validation is applied.
     *
     * $requireConfiguredSlot:
     * - true  => customer booking must use configured slot
     * - false => admin can use custom start/end time
     */
    public static function validateSlotForBooking(
        string $date,
        ?string $time,
        ?int $excludeVisitId = null,
        bool $requireConfiguredSlot = true
    ): ?string {

        /**
         * Existing date-only booking flow remains valid.
         */
        if ($time === null || $time === '') {
            return null;
        }

        $settings = self::settings();

        /**
         * The selected date must be an enabled working day.
         */
        $dayCfg = $settings->forDay(self::dayKey($date));

        if (! $dayCfg || empty($dayCfg['enabled'])) {
            return 'Selected date is not a working day.';
        }

        /**
         * Entire date blocked.
         */
        if (self::isDateFullyBlocked($date)) {
            return 'Selected date is blocked and not available for booking.';
        }

        /**
         * Check date-specific blocked slot.
         */
        if (self::isSlotBlocked($date, $time)) {
            return 'Selected time slot is blocked and not available for booking.';
        }

        /**
         * Customer-facing bookings must use an active
         * configured slot.
         */
        if ($requireConfiguredSlot) {

            $slot = JobTimeSlot::where('start_time', $time)
                ->where('is_active', true)
                ->first();

            if (! $slot) {
                return 'Selected time is not a valid active time slot.';
            }

            /**
             * Get the calculated slot end time.
             *
             * Do NOT use $slot->end_time because end_time
             * is calculated by JobTimeSlot::endTime().
             */
            $slotStart = Carbon::createFromFormat(
                'H:i',
                substr((string) $slot->start_time, 0, 5)
            );

            $slotEnd = Carbon::createFromFormat(
                'H:i',
                substr((string) $slot->endTime(), 0, 5)
            );

            $workingStart = Carbon::createFromFormat(
                'H:i',
                substr((string) $dayCfg['start'], 0, 5)
            );

            $workingEnd = Carbon::createFromFormat(
                'H:i',
                substr((string) $dayCfg['end'], 0, 5)
            );

            /**
             * The complete slot must be inside working hours.
             */
            if (
                $slotStart->lt($workingStart)
                || $slotEnd->gt($workingEnd)
            ) {
                return 'Selected time slot is outside working hours.';
            }
        }

        /**
         * Check capacity for this exact date + time.
         */
        $bookedForSlot = Visit::whereDate('scheduled_date', $date)
            ->where('scheduled_time', $time)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where('id', '!=', $excludeVisitId)
            )
            ->count();

        if ($bookedForSlot >= $settings->max_bookings_per_slot) {
            return 'This time slot is fully booked. Please choose another slot.';
        }

        /**
         * Check total capacity for the entire day.
         */
        $dayBooked = Visit::whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where('id', '!=', $excludeVisitId)
            )
            ->count();

        if ($dayBooked >= $settings->max_bookings_per_day) {
            return 'This date is fully booked. Please choose another date.';
        }

        return null;
    }

    /**
     * Check whether technician already has an overlapping job.
     *
     * Takes:
     * - slot duration
     * - existing visit duration
     * - configured buffer
     *
     * into account.
     */
    public static function hasTechnicianConflict(
        int $technicianId,
        string $date,
        ?string $time,
        ?int $excludeVisitId = null,
        ?int $durationMinutes = null
    ): bool {

        /**
         * Date-only visits do not have a time conflict.
         */
        if ($time === null || $time === '') {
            return false;
        }

        $buffer = self::settings()->buffer_minutes;

        /**
         * Use explicitly supplied duration when available.
         * Otherwise use the configured slot duration.
         */
        $duration = $durationMinutes
            ?? self::slotDurationMinutes($time);

        $newStart = Carbon::parse($date . ' ' . $time);

        $newEnd = $newStart
            ->copy()
            ->addMinutes($duration + $buffer);

        $newStartBuffered = $newStart
            ->copy()
            ->subMinutes($buffer);

        /**
         * Get existing technician visits for this date.
         */
        $existing = Visit::where('technician_id', $technicianId)
            ->whereDate('scheduled_date', $date)
            ->whereNotNull('scheduled_time')
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where('id', '!=', $excludeVisitId)
            )
            ->get([
                'id',
                'scheduled_date',
                'scheduled_time',
                'duration_minutes',
            ]);

        foreach ($existing as $visit) {

            $exStart = Carbon::parse(
                $visit->scheduled_date->toDateString()
                . ' '
                . $visit->scheduled_time
            );

            /**
             * Existing visit duration:
             * use stored duration if available,
             * otherwise use configured slot duration.
             */
            $exDuration = $visit->duration_minutes
                ?? self::slotDurationMinutes($visit->scheduled_time);

            $exEnd = $exStart
                ->copy()
                ->addMinutes($exDuration);

            /**
             * Check overlap.
             */
            if (
                $newStartBuffered->lt($exEnd)
                && $newEnd->gt($exStart)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get minutes between two HH:mm strings.
     *
     * End time must be after start time.
     */
    public static function minutesBetween(
        string $startTime,
        string $endTime
    ): int {
        return Carbon::parse($startTime)
            ->diffInMinutes(Carbon::parse($endTime));
    }
}