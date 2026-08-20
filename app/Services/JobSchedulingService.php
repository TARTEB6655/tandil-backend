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
    /**
     * These statuses do not consume booking capacity
     * and do not create technician conflicts.
     */
    private const NON_BLOCKING_STATUSES = [
        'rejected',
        'cancelled',
        'canceled',
    ];

    /**
     * Normalize any time string to HH:mm (24h) for capacity matching.
     * Handles "09:00", "09:00:00", "9:00 AM", etc.
     */
    public static function normalizeTimeHi(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i');
        } catch (\Throwable $e) {
            if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
                return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
            }

            return null;
        }
    }

    /**
     * How many active bookings currently occupy this date + start time.
     * Counts visits (any HH:mm / HH:mm:ss storage) plus paid shop order-items
     * whose booking_slot maps to this start when the visit time is still missing.
     */
    public static function countBookingsForSlot(
        string $date,
        string $time,
        ?int $excludeVisitId = null
    ): int {
        $slotHi = self::normalizeTimeHi($time);
        if ($slotHi === null) {
            return 0;
        }

        $visits = Visit::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where('id', '!=', $excludeVisitId)
            )
            ->get(['id', 'scheduled_time', 'order_item_id']);

        $countedOrderItemIds = [];
        $count = 0;

        foreach ($visits as $visit) {
            $visitHi = self::normalizeTimeHi(
                is_string($visit->scheduled_time) ? $visit->scheduled_time : null
            );
            if ($visitHi === $slotHi) {
                $count++;
                if ($visit->order_item_id) {
                    $countedOrderItemIds[(int) $visit->order_item_id] = true;
                }
            }
        }

        // Paid shop line bookings that never got scheduled_time on the visit
        // (or have no visit yet) still consume slot capacity.
        $orderItems = \App\Models\OrderItem::query()
            ->whereDate('booking_date', $date)
            ->whereNotNull('booking_slot')
            ->where('booking_slot', '!=', '')
            ->whereHas('order', function ($q) {
                $q->whereNull('package_id')
                    ->where('payment_status', 'paid');
            })
            ->with(['visit:id,order_item_id,scheduled_time,status'])
            ->get(['id', 'booking_slot', 'booking_date']);

        foreach ($orderItems as $item) {
            if (isset($countedOrderItemIds[(int) $item->id])) {
                continue;
            }

            $itemHi = \App\Support\ShopBookingSlotHelper::slotStartTime24h($item->booking_slot);
            if ($itemHi !== $slotHi) {
                continue;
            }

            $visit = $item->relationLoaded('visit') ? $item->visit : null;
            if ($visit) {
                $status = strtolower((string) ($visit->status ?? ''));
                if (in_array($status, self::NON_BLOCKING_STATUSES, true)) {
                    continue;
                }
                $visitHi = self::normalizeTimeHi(
                    is_string($visit->scheduled_time) ? $visit->scheduled_time : null
                );
                if ($visitHi === $slotHi) {
                    continue; // already counted above
                }
                if ($visitHi !== null) {
                    // Visit booked a different clock time — don't double-count this item.
                    continue;
                }
            }

            $count++;
        }

        return $count;
    }

    /**
     * Precompute booked counts for every HH:mm on a date (one visit query + order items).
     *
     * @return array<string, int> map of "H:i" => count
     */
    public static function bookedCountsBySlotForDate(string $date): array
    {
        $counts = [];

        $visits = Visit::query()
            ->whereDate('scheduled_date', $date)
            ->whereNotIn('status', self::NON_BLOCKING_STATUSES)
            ->get(['id', 'scheduled_time', 'order_item_id']);

        $countedOrderItemIds = [];
        foreach ($visits as $visit) {
            $hi = self::normalizeTimeHi(
                is_string($visit->scheduled_time) ? $visit->scheduled_time : null
            );
            if ($hi === null) {
                continue;
            }
            $counts[$hi] = ($counts[$hi] ?? 0) + 1;
            if ($visit->order_item_id) {
                $countedOrderItemIds[(int) $visit->order_item_id] = true;
            }
        }

        $orderItems = \App\Models\OrderItem::query()
            ->whereDate('booking_date', $date)
            ->whereNotNull('booking_slot')
            ->where('booking_slot', '!=', '')
            ->whereHas('order', function ($q) {
                $q->whereNull('package_id')
                    ->where('payment_status', 'paid');
            })
            ->with(['visit:id,order_item_id,scheduled_time,status'])
            ->get(['id', 'booking_slot']);

        foreach ($orderItems as $item) {
            if (isset($countedOrderItemIds[(int) $item->id])) {
                continue;
            }
            $hi = \App\Support\ShopBookingSlotHelper::slotStartTime24h($item->booking_slot);
            if ($hi === null) {
                continue;
            }
            $visit = $item->relationLoaded('visit') ? $item->visit : null;
            if ($visit) {
                $status = strtolower((string) ($visit->status ?? ''));
                if (in_array($status, self::NON_BLOCKING_STATUSES, true)) {
                    continue;
                }
                $visitHi = self::normalizeTimeHi(
                    is_string($visit->scheduled_time) ? $visit->scheduled_time : null
                );
                if ($visitHi !== null) {
                    continue;
                }
            }
            $counts[$hi] = ($counts[$hi] ?? 0) + 1;
        }

        return $counts;
    }
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
        return strtolower(
            Carbon::parse($date)->format('D')
        );
    }

    /**
     * Check whether the complete date is blocked.
     */
    public static function isDateFullyBlocked(string $date): bool
    {
        return JobBlockedDate::whereDate('date', $date)
            ->where(
                'block_type',
                JobBlockedDate::TYPE_FULL_DAY
            )
            ->exists();
    }

    /**
     * Check whether a particular time slot is blocked on a date.
     */
    public static function isSlotBlocked(
        string $date,
        string $time
    ): bool {
        return JobBlockedDate::whereDate('date', $date)
            ->where(
                'block_type',
                JobBlockedDate::TYPE_TIME_SLOT
            )
            ->where('time', $time)
            ->exists();
    }

    /**
     * Get all blocked dates / slots for a specific date.
     *
     * This allows the API/frontend to know exactly why
     * a date or slot is unavailable.
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

                    'date' => Carbon::parse(
                        $block->date
                    )->toDateString(),

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
        $slot = JobTimeSlot::where(
            'start_time',
            $time
        )->first();

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
     * - The slot is active
     * - The complete slot falls within working hours
     * - The slot itself is not blocked
     * - Slot capacity is available
     * - Daily capacity is available
     *
     * IMPORTANT:
     * If the entire date is blocked, slots are still returned
     * with:
     *
     *     blocked = true
     *     available = false
     *
     * This allows the frontend to clearly understand that
     * the selected date itself is blocked.
     */
    public static function availableSlots(string $date): array
    {
        $settings = self::settings();

        /**
         * Get working-day configuration.
         */
        $dayCfg = $settings->forDay(
            self::dayKey($date)
        );

        /**
         * If the day is disabled / not configured,
         * there are no slots.
         */
        if (! $dayCfg || empty($dayCfg['enabled'])) {
            return [];
        }

        /**
         * Check whether the entire date is blocked.
         *
         * IMPORTANT:
         * Do NOT return [] here.
         *
         * We still want the API to return the configured
         * slots with blocked=true so frontend can display
         * the selected date as blocked.
         */
        $dateBlocked = self::isDateFullyBlocked($date);

        /**
         * Get total bookings for the date.
         */
        $dayBookedCount = Visit::whereDate(
            'scheduled_date',
            $date
        )
            ->whereNotIn(
                'status',
                self::NON_BLOCKING_STATUSES
            )
            ->count();

        $dayFull =
            $dayBookedCount >=
            $settings->max_bookings_per_day;

        $bookedBySlot = self::bookedCountsBySlotForDate($date);

        /**
         * Get all active configured slots.
         */
        $slots = JobTimeSlot::where(
            'is_active',
            true
        )
            ->orderBy('sort_order')
            ->orderBy('start_time')
            ->get()
            ->filter(function (JobTimeSlot $slot) use ($dayCfg) {

                /**
                 * Slot start.
                 */
                $slotStart = Carbon::createFromFormat(
                    'H:i',
                    substr(
                        (string) $slot->start_time,
                        0,
                        5
                    )
                );

                /**
                 * Slot end calculated from model.
                 */
                $slotEnd = Carbon::createFromFormat(
                    'H:i',
                    substr(
                        (string) $slot->endTime(),
                        0,
                        5
                    )
                );

                /**
                 * Working hours start.
                 */
                $workingStart = Carbon::createFromFormat(
                    'H:i',
                    substr(
                        (string) $dayCfg['start'],
                        0,
                        5
                    )
                );

                /**
                 * Working hours end.
                 */
                $workingEnd = Carbon::createFromFormat(
                    'H:i',
                    substr(
                        (string) $dayCfg['end'],
                        0,
                        5
                    )
                );

                /**
                 * Complete slot must be inside
                 * working hours.
                 */
                return $slotStart->greaterThanOrEqualTo(
                    $workingStart
                )
                    && $slotEnd->lessThanOrEqualTo(
                        $workingEnd
                    );
            })
            ->values();

        /**
         * Calculate availability for every valid slot.
         */
        return $slots
            ->map(function (JobTimeSlot $slot) use (
                $date,
                $settings,
                $dayFull,
                $dateBlocked,
                $bookedBySlot
            ) {

                /**
                 * Check whether this exact time slot
                 * is blocked for this date.
                 */
                $slotBlocked = self::isSlotBlocked(
                    $date,
                    $slot->start_time
                );

                $slotHi = self::normalizeTimeHi((string) $slot->start_time) ?? substr((string) $slot->start_time, 0, 5);
                $booked = (int) ($bookedBySlot[$slotHi] ?? 0);

                /**
                 * Calculate remaining capacity.
                 */
                $remaining = max(
                    0,
                    $settings->max_bookings_per_slot - $booked
                );

                /**
                 * A slot is blocked if:
                 *
                 * 1. Entire date is blocked
                 * OR
                 * 2. Specific time slot is blocked
                 */
                $blocked =
                    $dateBlocked ||
                    $slotBlocked;

                /**
                 * Slot is available only if:
                 *
                 * - date is not blocked
                 * - slot is not blocked
                 * - day capacity is available
                 * - slot capacity is available
                 */
                $available =
                    ! $blocked
                    && ! $dayFull
                    && $remaining > 0;

                return [
                    'id' => $slot->id,

                    'start_time' => $slotHi,

                    'end_time' => $slot->endTime(),

                    'duration_minutes' => (int) $slot->duration_minutes,

                    'booked_count' => $booked,

                    'remaining' => $remaining,

                    'max_bookings' => (int) $settings->max_bookings_per_slot,

                    /**
                     * true when either the complete date
                     * or this specific slot is blocked.
                     */
                    'blocked' => $blocked,

                    /**
                     * More specific information.
                     */
                    'date_blocked' => $dateBlocked,

                    'slot_blocked' => $slotBlocked,

                    'available' => $available,
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
         * Selected date must be an enabled working day.
         */
        $dayCfg = $settings->forDay(
            self::dayKey($date)
        );

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
         * Specific time slot blocked.
         */
        if (self::isSlotBlocked($date, $time)) {
            return 'Selected time slot is blocked and not available for booking.';
        }

        /**
         * Requested start time must fall within the day's working hours,
         * regardless of $requireConfiguredSlot — admin's custom-end-time
         * screen bypasses the "must be a pre-configured slot" rule below,
         * not the working-hours window itself.
         */
        $workingStart = Carbon::createFromFormat(
            'H:i',
            substr((string) $dayCfg['start'], 0, 5)
        );
        $workingEnd = Carbon::createFromFormat(
            'H:i',
            substr((string) $dayCfg['end'], 0, 5)
        );
        $requestedStart = Carbon::createFromFormat(
            'H:i',
            substr($time, 0, 5)
        );
        if ($requestedStart->lt($workingStart) || $requestedStart->gte($workingEnd)) {
            return 'Selected time is outside working hours.';
        }

        /**
         * Customer-facing bookings must use
         * an active configured slot.
         */
        if ($requireConfiguredSlot) {

            $slot = JobTimeSlot::where(
                'start_time',
                $time
            )
                ->where('is_active', true)
                ->first();

            if (! $slot) {
                return 'Selected time is not a valid active time slot.';
            }

            /**
             * Slot start.
             */
            $slotStart = Carbon::createFromFormat(
                'H:i',
                substr(
                    (string) $slot->start_time,
                    0,
                    5
                )
            );

            /**
             * Slot end.
             */
            $slotEnd = Carbon::createFromFormat(
                'H:i',
                substr(
                    (string) $slot->endTime(),
                    0,
                    5
                )
            );

            /**
             * Working hours start.
             */
            $workingStart = Carbon::createFromFormat(
                'H:i',
                substr(
                    (string) $dayCfg['start'],
                    0,
                    5
                )
            );

            /**
             * Working hours end.
             */
            $workingEnd = Carbon::createFromFormat(
                'H:i',
                substr(
                    (string) $dayCfg['end'],
                    0,
                    5
                )
            );

            /**
             * Complete slot must be inside working hours.
             */
            if (
                $slotStart->lt($workingStart)
                || $slotEnd->gt($workingEnd)
            ) {
                return 'Selected time slot is outside working hours.';
            }
        }

        /**
         * Check capacity for this exact date/time.
         */
        $bookedForSlot = self::countBookingsForSlot($date, $time, $excludeVisitId);

        if (
            $bookedForSlot >=
            $settings->max_bookings_per_slot
        ) {
            return 'This time slot is fully booked. Please choose another slot.';
        }

        /**
         * Check total capacity for the entire day.
         */
        $dayBooked = Visit::whereDate(
            'scheduled_date',
            $date
        )
            ->whereNotIn(
                'status',
                self::NON_BLOCKING_STATUSES
            )
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where(
                    'id',
                    '!=',
                    $excludeVisitId
                )
            )
            ->count();

        if (
            $dayBooked >=
            $settings->max_bookings_per_day
        ) {
            return 'This date is fully booked. Please choose another date.';
        }

        return null;
    }

    /**
     * Check whether technician already has
     * an overlapping job.
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
         * Otherwise use configured slot duration.
         */
        $duration = $durationMinutes
            ?? self::slotDurationMinutes($time);

        /**
         * New visit start.
         */
        $newStart = Carbon::parse(
            $date . ' ' . $time
        );

        /**
         * New visit end including buffer.
         */
        $newEnd = $newStart
            ->copy()
            ->addMinutes(
                $duration + $buffer
            );

        /**
         * New visit start including buffer.
         */
        $newStartBuffered = $newStart
            ->copy()
            ->subMinutes($buffer);

        /**
         * Get existing technician visits for this date.
         */
        $existing = Visit::where(
            'technician_id',
            $technicianId
        )
            ->whereDate(
                'scheduled_date',
                $date
            )
            ->whereNotNull('scheduled_time')
            ->whereNotIn(
                'status',
                self::NON_BLOCKING_STATUSES
            )
            ->when(
                $excludeVisitId,
                fn ($q) => $q->where(
                    'id',
                    '!=',
                    $excludeVisitId
                )
            )
            ->get([
                'id',
                'scheduled_date',
                'scheduled_time',
                'duration_minutes',
            ]);

        foreach ($existing as $visit) {

            /**
             * Existing visit start.
             */
            $exStart = Carbon::parse(
                $visit->scheduled_date->toDateString()
                . ' '
                . $visit->scheduled_time
            );

            /**
             * Existing visit duration.
             */
            $exDuration = $visit->duration_minutes
                ?? self::slotDurationMinutes(
                    $visit->scheduled_time
                );

            /**
             * Existing visit end.
             */
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
            ->diffInMinutes(
                Carbon::parse($endTime)
            );
    }
}