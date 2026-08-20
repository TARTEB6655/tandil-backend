<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\ParsesPutMultipartFormFields;
use App\Models\JobBlockedDate;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotification;
use App\Services\JobSchedulingService;
use App\Support\OrderToVisitDispatcher;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Admin "Job Scheduling" screens: Working hours & capacity, Time slots,
 * Blocked dates & slots, Jobs calendar.
 */
class JobSchedulingController extends Controller
{
    use ParsesPutMultipartFormFields;

    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * GET /api/admin/job-scheduling/working-hours
     */
    public function getWorkingHours()
    {
        $settings = JobSchedulingService::settings();

        return ApiResponse::success('Working hours & capacity retrieved successfully.', $this->settingsPayload($settings));
    }

    /**
     * PUT /api/admin/job-scheduling/working-hours
     * PHP does not populate $_POST for PUT + multipart/form-data, so Postman's
     * form-data body for this request needs the raw body re-parsed here.
     */
    public function updateWorkingHours(Request $request)
    {
        $this->mergePutMultipartFormFields($request);

        $request->validate([
            'working_hours' => 'nullable|array',
            'working_hours.*.day' => 'required_with:working_hours|string|in:'.implode(',', self::DAY_KEYS),
            'working_hours.*.enabled' => 'required_with:working_hours|boolean',
            'working_hours.*.start' => 'required_with:working_hours|date_format:H:i',
            'working_hours.*.end' => 'required_with:working_hours|date_format:H:i|after:working_hours.*.start',
            'max_bookings_per_slot' => 'nullable|integer|min:1',
            'max_bookings_per_day' => 'nullable|integer|min:1',
            'buffer_minutes' => 'nullable|integer|min:0|max:240',
        ]);

        $settings = JobSchedulingService::settings();

        if ($request->has('working_hours')) {
            $settings->working_hours = $request->input('working_hours');
        }
        if ($request->has('max_bookings_per_slot')) {
            $settings->max_bookings_per_slot = (int) $request->input('max_bookings_per_slot');
        }
        if ($request->has('max_bookings_per_day')) {
            $settings->max_bookings_per_day = (int) $request->input('max_bookings_per_day');
        }
        if ($request->has('buffer_minutes')) {
            $settings->buffer_minutes = (int) $request->input('buffer_minutes');
        }
        $settings->save();

        return ApiResponse::success('Working hours & capacity updated successfully.', $this->settingsPayload($settings->fresh()));
    }

    private function settingsPayload(JobSchedulingSetting $settings): array
    {
        return [
            'working_hours' => $settings->working_hours ?: JobSchedulingSetting::defaultWorkingHours(),
            'max_bookings_per_slot' => $settings->max_bookings_per_slot,
            'max_bookings_per_day' => $settings->max_bookings_per_day,
            'buffer_minutes' => $settings->buffer_minutes,
        ];
    }

    /**
     * GET /api/admin/job-scheduling/time-slots
     */
    public function listTimeSlots()
    {
        $slots = JobTimeSlot::orderBy('sort_order')->orderBy('start_time')->get();

        return ApiResponse::success('Time slots retrieved successfully.', $slots->map(fn (JobTimeSlot $s) => $this->timeSlotPayload($s))->values());
    }

    /**
     * POST /api/admin/job-scheduling/time-slots
     */
    public function addTimeSlot(Request $request)
    {
        $request->validate([
            'start_time' => 'required|date_format:H:i',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'is_active' => 'nullable|boolean',
        ]);

        if (JobTimeSlot::where('start_time', $request->input('start_time'))->exists()) {
            return ApiResponse::error('A time slot already exists at this start time.', 422);
        }

        $slot = JobTimeSlot::create([
            'start_time' => $request->input('start_time'),
            'duration_minutes' => (int) $request->input('duration_minutes', 60),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'sort_order' => (int) JobTimeSlot::max('sort_order') + 1,
        ]);

        return ApiResponse::success('Time slot added successfully.', $this->timeSlotPayload($slot), 201);
    }

    /**
     * POST /api/admin/job-scheduling/time-slots/{id}/toggle
     */
    public function toggleTimeSlot(int $id)
    {
        $slot = JobTimeSlot::find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 404);
        }

        $slot->is_active = ! $slot->is_active;
        $slot->save();

        return ApiResponse::success('Time slot status updated.', $this->timeSlotPayload($slot));
    }

    /**
     * DELETE /api/admin/job-scheduling/time-slots/{id}
     */
    public function deleteTimeSlot(int $id)
    {
        $slot = JobTimeSlot::find($id);
        if (! $slot) {
            return ApiResponse::error('Time slot not found.', 404);
        }

        $slot->delete();

        return ApiResponse::success('Time slot deleted successfully.');
    }

    private function timeSlotPayload(JobTimeSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'start_time' => $slot->start_time,
            'end_time' => $slot->endTime(),
            'duration_minutes' => $slot->duration_minutes,
            'is_active' => (bool) $slot->is_active,
            'sort_order' => $slot->sort_order,
        ];
    }

    /**
     * GET /api/admin/job-scheduling/blocked-dates
     */
    public function listBlockedDates(Request $request)
    {
        $query = JobBlockedDate::orderBy('date', 'desc');
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->input('to'));
        }

        return ApiResponse::success('Blocked dates retrieved successfully.', $query->get()->map(fn (JobBlockedDate $b) => $this->blockedDatePayload($b))->values());
    }

    /**
     * POST /api/admin/job-scheduling/blocked-dates
     */
    public function addBlockedDate(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'block_type' => 'required|string|in:full_day,time_slot',
            'time' => 'required_if:block_type,time_slot|nullable|date_format:H:i',
            'reason' => 'nullable|string|max:255',
        ]);

        $block = JobBlockedDate::create([
            'date' => $request->input('date'),
            'block_type' => $request->input('block_type'),
            'time' => $request->input('block_type') === 'time_slot' ? $request->input('time') : null,
            'reason' => $request->input('reason'),
        ]);

        return ApiResponse::success('Block added successfully.', $this->blockedDatePayload($block), 201);
    }

    /**
     * DELETE /api/admin/job-scheduling/blocked-dates/{id}
     */
    public function deleteBlockedDate(int $id)
    {
        $block = JobBlockedDate::find($id);
        if (! $block) {
            return ApiResponse::error('Block not found.', 404);
        }

        $block->delete();

        return ApiResponse::success('Block removed successfully.');
    }

    private function blockedDatePayload(JobBlockedDate $block): array
    {
        return [
            'id' => $block->id,
            'date' => $block->date->toDateString(),
            'block_type' => $block->block_type,
            'time' => $block->time,
            'reason' => $block->reason,
        ];
    }

    /**
     * GET /api/admin/job-scheduling/calendar?view=day|week|month&date=YYYY-MM-DD
     * Jobs calendar with technician-overlap flag per job (red "Technician overlap" warning).
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'view' => 'nullable|string|in:day,week,month',
            'date' => 'nullable|date',
        ]);

        $view = $request->input('view', 'day');
        $anchor = $request->filled('date') ? Carbon::parse($request->input('date')) : Carbon::today();

        [$from, $to] = match ($view) {
            'week' => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
            'month' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            default => [$anchor->copy(), $anchor->copy()],
        };

        $visits = Visit::whereDate('scheduled_date', '>=', $from->toDateString())
            ->whereDate('scheduled_date', '<=', $to->toDateString())
            ->with([
                'technician:id,name',
                'supervisor:id,name',
                'subscription.client:id,name',
                'order.user:id,name',
                'orderItem.product:id,name,job_duration',
            ])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get()
            ->map(fn (Visit $v) => OrderToVisitDispatcher::syncVisitScheduleFromLinkedOrder($v))
            ->values();

        // Pairwise overlap within this calendar window (same technician + overlapping times).
        $overlapWith = $this->buildTechnicianOverlapMap($visits);

        $jobs = $visits->map(function (Visit $v) use ($overlapWith) {
            $conflictingIds = $overlapWith[$v->id] ?? [];
            $hasOverlap = $conflictingIds !== [];

            return $this->calendarJobPayload($v, $hasOverlap, $conflictingIds);
        })->values();

        return ApiResponse::success('Jobs calendar retrieved successfully.', [
            'view' => $view,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => $jobs->count(),
            'overlap_count' => $jobs->where('technician_overlap', true)->count(),
            'jobs' => $jobs,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Visit>  $visits
     * @return array<int, list<int>> visit_id => conflicting visit ids
     */
    private function buildTechnicianOverlapMap($visits): array
    {
        $map = [];
        $candidates = $visits
            ->filter(fn (Visit $v) => $v->technician_id && $v->scheduled_time && $v->scheduled_date)
            ->values();

        for ($i = 0; $i < $candidates->count(); $i++) {
            /** @var Visit $a */
            $a = $candidates[$i];
            for ($j = $i + 1; $j < $candidates->count(); $j++) {
                /** @var Visit $b */
                $b = $candidates[$j];
                if ((int) $a->technician_id !== (int) $b->technician_id) {
                    continue;
                }
                if ($a->scheduled_date->toDateString() !== $b->scheduled_date->toDateString()) {
                    continue;
                }
                if (! $this->visitsTimeWindowsOverlap($a, $b)) {
                    continue;
                }
                $map[$a->id][] = (int) $b->id;
                $map[$b->id][] = (int) $a->id;
            }
        }

        foreach ($map as $id => $ids) {
            $map[$id] = array_values(array_unique($ids));
        }

        return $map;
    }

    private function visitsTimeWindowsOverlap(Visit $a, Visit $b): bool
    {
        $aStart = Carbon::parse($a->scheduled_date->toDateString().' '.$a->scheduled_time);
        $bStart = Carbon::parse($b->scheduled_date->toDateString().' '.$b->scheduled_time);
        $aEnd = $aStart->copy()->addMinutes($this->resolvedDurationMinutes($a));
        $bEnd = $bStart->copy()->addMinutes($this->resolvedDurationMinutes($b));

        return $aStart->lt($bEnd) && $bStart->lt($aEnd);
    }

    private function resolvedDurationMinutes(Visit $v): int
    {
        if ($v->duration_minutes !== null && (int) $v->duration_minutes > 0) {
            return (int) $v->duration_minutes;
        }

        $end = $this->computeEndTime($v);
        if ($end && $v->scheduled_time) {
            $mins = JobSchedulingService::minutesBetween(
                substr((string) $v->scheduled_time, 0, 5),
                $end
            );
            if ($mins > 0) {
                return $mins;
            }
        }

        return 60;
    }

    /**
     * @param  list<int>  $conflictingIds
     * @return array<string, mixed>
     */
    private function calendarJobPayload(Visit $v, bool $hasOverlap, array $conflictingIds = []): array
    {
        $endTime = $this->computeEndTime($v);
        $title = $this->jobTitleFromNotes((string) $v->notes, $v->id);
        $client = $this->resolveJobClient($v);
        $technician = $v->technician ? ['id' => $v->technician->id, 'name' => $v->technician->name] : null;
        $supervisor = $v->supervisor ? ['id' => $v->supervisor->id, 'name' => $v->supervisor->name] : null;

        return [
            'id' => $v->id,
            'title' => $title,
            'scheduled_date' => $v->scheduled_date?->toDateString(),
            'scheduled_time' => $v->scheduled_time,
            'end_time' => $endTime,
            'time_slot' => $this->formatCalendarTimeSlot($v->scheduled_time, $endTime),
            'status' => $v->status,
            'status_label' => $this->jobStatusLabel($v->status),
            'notes' => $v->notes,
            'technician' => $technician,
            'technician_name' => $technician['name'] ?? null,
            'supervisor' => $supervisor,
            'supervisor_name' => $supervisor['name'] ?? null,
            'client' => $client,
            'client_name' => $client['name'] ?? null,
            'assignees_label' => $this->assigneesLabel($client, $supervisor, $technician),
            'technician_overlap' => $hasOverlap,
            'overlap_warning' => $hasOverlap ? 'Technician overlap' : null,
            'overlap_with_job_ids' => $conflictingIds,
        ];
    }

    private function resolveJobClient(Visit $v): ?array
    {
        if ($v->subscription?->client) {
            return ['id' => $v->subscription->client->id, 'name' => $v->subscription->client->name];
        }
        if ($v->order?->user) {
            return ['id' => $v->order->user->id, 'name' => $v->order->user->name];
        }
        $guest = trim((string) ($v->order?->guest_full_name ?? ''));
        if ($guest !== '') {
            return ['id' => null, 'name' => $guest];
        }

        return null;
    }

    private function assigneesLabel(?array $client, ?array $supervisor, ?array $technician): ?string
    {
        $parts = array_values(array_filter([
            $client['name'] ?? null,
            $supervisor['name'] ?? null,
            $technician['name'] ?? null,
        ], static fn ($name) => is_string($name) && trim($name) !== ''));

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function formatCalendarTimeSlot(?string $start, ?string $end): ?string
    {
        if (! $start) {
            return null;
        }
        try {
            $startLabel = Carbon::parse($start)->format('g:i A');
            if (! $end) {
                return $startLabel;
            }
            $endLabel = Carbon::parse($end)->format('g:i A');

            return $startLabel.' – '.$endLabel;
        } catch (\Throwable $e) {
            return $end ? $start.' – '.$end : $start;
        }
    }

    private function jobStatusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'pending' => 'Pending',
            'scheduled', 'confirmed', 'accepted' => 'Confirmed',
            'in_progress', 'started' => 'In Progress',
            'completed', 'done' => 'Completed',
            'cancelled', 'canceled', 'rejected' => 'Cancelled',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : 'Pending',
        };
    }

    /**
     * Reusable filter for orphaned junk visits cluttering the Jobs calendar:
     * no subscription, no order, no notes, no scheduled time, no
     * technician/area/supervisor, and still "pending". Never matches a
     * real booking — every real Create Visit call requires subscription_id.
     */
    private function orphanJobsQuery()
    {
        return Visit::whereNull('subscription_id')
            ->whereNull('order_id')
            ->whereNull('notes')
            ->whereNull('scheduled_time')
            ->whereNull('technician_id')
            ->whereNull('area_id')
            ->whereNull('supervisor_id')
            ->where('status', 'pending');
    }

    /**
     * GET /api/admin/job-scheduling/jobs/orphans
     * Preview: count + up to 500 ids of blank/junk visits that would be
     * removed by DELETE /api/admin/job-scheduling/jobs/orphans. Read-only —
     * run this first to confirm the count before deleting.
     */
    public function previewOrphanJobs()
    {
        $query = $this->orphanJobsQuery();

        return ApiResponse::success('Orphan job preview retrieved successfully.', [
            'count' => $query->count(),
            'ids' => $query->orderBy('id')->limit(500)->pluck('id'),
        ]);
    }

    /**
     * DELETE /api/admin/job-scheduling/jobs/orphans
     * Permanently deletes every visit matching orphanJobsQuery(). Destructive —
     * call GET .../jobs/orphans first to confirm the count/ids.
     */
    public function deleteOrphanJobs()
    {
        $deleted = $this->orphanJobsQuery()->delete();

        return ApiResponse::success('Orphan jobs deleted successfully.', [
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * GET /api/admin/job-scheduling/jobs/{id}
     * Booking detail screen: tap a job on the Jobs calendar. "Save changes"
     * and "Reschedule & notify" both call PUT /api/admin/job-scheduling/jobs/{id}
     * (updateBookingDetail below) — same five fields shown on this screen.
     */
    public function bookingDetail(int $id)
    {
        $visit = Visit::with([
            'technician:id,name',
            'supervisor:id,name',
            'subscription.client:id,name',
            'order.user:id,name',
            'orderItem.product:id,name,job_duration',
        ])->find($id);
        if (! $visit) {
            return ApiResponse::error('Job not found.', 404);
        }

        $visit = OrderToVisitDispatcher::syncVisitScheduleFromLinkedOrder($visit);

        $endTime = $this->computeEndTime($visit);
        $scheduledDate = $visit->scheduled_date?->toDateString();
        $currentScheduleLabel = $scheduledDate
            ? Carbon::parse($scheduledDate)->format('D, M j').($visit->scheduled_time ? ' · '.$visit->scheduled_time.($endTime ? '–'.$endTime : '') : '')
            : null;

        $overlap = ($visit->technician_id && $visit->scheduled_time && $scheduledDate)
            ? JobSchedulingService::hasTechnicianConflict(
                (int) $visit->technician_id,
                $scheduledDate,
                $visit->scheduled_time,
                $visit->id,
                $this->resolvedDurationMinutes($visit)
            )
            : false;

        $client = $this->resolveJobClient($visit);
        $technician = $visit->technician ? ['id' => $visit->technician->id, 'name' => $visit->technician->name] : null;
        $supervisor = $visit->supervisor ? ['id' => $visit->supervisor->id, 'name' => $visit->supervisor->name] : null;

        return ApiResponse::success('Booking detail retrieved successfully.', [
            'id' => $visit->id,
            'title' => $this->jobTitleFromNotes((string) $visit->notes, $visit->id),
            'client' => $client,
            'technician' => $technician,
            'technician_name' => $technician['name'] ?? null,
            'supervisor' => $supervisor,
            'supervisor_name' => $supervisor['name'] ?? null,
            'assignees_label' => $this->assigneesLabel($client, $supervisor, $technician),
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $visit->scheduled_time,
            'end_time' => $endTime,
            'time_slot' => $this->formatCalendarTimeSlot($visit->scheduled_time, $endTime),
            'duration_minutes' => $visit->duration_minutes ?? $this->resolvedDurationMinutes($visit),
            'current_schedule_label' => $currentScheduleLabel ? 'Currently '.$currentScheduleLabel : null,
            'status' => $visit->status,
            'status_label' => $this->jobStatusLabel($visit->status),
            'notes' => $visit->notes,
            'internal_notes' => $visit->internal_notes,
            'technician_overlap' => $overlap,
            'overlap_warning' => $overlap ? 'Technician overlap' : null,
        ]);
    }

    /**
     * PUT /api/admin/job-scheduling/jobs/{id}
     * Booking detail screen: "Save changes" and "Reschedule & notify" both call
     * this. Only the fields shown on that screen are accepted: date, start, end,
     * technician_id, internal_notes. Re-validates the slot/technician conflict
     * and — when the date or start time actually changes — notifies the client
     * + assigned technician that the job was rescheduled.
     *
     * internal_notes is a dedicated column, separate from `notes` (which stores
     * the client-facing pipe-delimited job title/service string parsed by
     * jobTitleFromNotes()) — writing this screen's notes field into `notes`
     * would silently overwrite the job title.
     */
    public function updateBookingDetail(Request $request, int $id)
    {
        $this->mergePutMultipartFormFields($request);

        $visit = Visit::with('subscription.client')->find($id);
        if (! $visit) {
            return ApiResponse::error('Job not found.', 404);
        }

        $request->validate([
            'date' => 'nullable|date',
            'start' => 'nullable|date_format:H:i',
            'end' => 'nullable|date_format:H:i|after:start',
            'technician_id' => 'nullable|exists:users,id',
            'internal_notes' => 'nullable|string|max:5000',
        ]);

        $effectiveDate = $request->input('date', optional($visit->scheduled_date)->toDateString());
        $effectiveStart = $request->has('start') ? $request->input('start') : $visit->scheduled_time;

        $durationMinutes = $visit->duration_minutes;
        if ($request->filled('end')) {
            $durationMinutes = $effectiveStart ? JobSchedulingService::minutesBetween($effectiveStart, $request->input('end')) : null;
        }

        if ($request->has('date') || $request->has('start') || $request->has('technician_id')) {
            if ($request->has('date') || $request->has('start')) {
                // Admin's custom end time (this screen) bypasses the "must be a configured slot" restriction.
                $requireConfiguredSlot = ! $request->filled('end');
                $schedulingError = JobSchedulingService::validateSlotForBooking($effectiveDate, $effectiveStart, $visit->id, $requireConfiguredSlot);
                if ($schedulingError) {
                    return ApiResponse::error($schedulingError, 422);
                }
            }

            $effectiveTechnicianId = $request->has('technician_id') ? $request->input('technician_id') : $visit->technician_id;
            if ($effectiveTechnicianId && JobSchedulingService::hasTechnicianConflict((int) $effectiveTechnicianId, $effectiveDate, $effectiveStart, $visit->id, $durationMinutes)) {
                return ApiResponse::error('Selected technician is already booked for this date and time.', 422);
            }
        }

        $oldScheduledDate = optional($visit->scheduled_date)->toDateString();
        $oldScheduledTime = $visit->scheduled_time;

        if ($request->has('date')) {
            $visit->scheduled_date = $request->input('date');
        }
        if ($request->has('start')) {
            $visit->scheduled_time = $request->input('start');
        }
        if ($request->filled('end')) {
            $visit->duration_minutes = $durationMinutes;
        }
        if ($request->has('technician_id')) {
            $visit->technician_id = $request->input('technician_id');
        }
        if ($request->has('internal_notes')) {
            $visit->internal_notes = $request->input('internal_notes');
        }

        $visit->save();

        try {
            $newScheduledDate = optional($visit->scheduled_date)->toDateString();
            $rescheduled = ($request->has('date') && $oldScheduledDate !== $newScheduledDate)
                || ($request->has('start') && $oldScheduledTime !== $visit->scheduled_time);

            if ($rescheduled) {
                $when = $newScheduledDate.($visit->scheduled_time ? ' '.$visit->scheduled_time : '');
                $meta = ['type' => 'booking_rescheduled', 'visit_id' => $visit->id, 'scheduled_date' => $newScheduledDate, 'scheduled_time' => $visit->scheduled_time];

                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification('Visit Rescheduled', "Your visit has been rescheduled to {$when}.", $meta));
                }
                if ($visit->technician_id) {
                    User::find($visit->technician_id)?->notify(new AdminNotification('Visit Rescheduled', "Visit #{$visit->id} has been rescheduled to {$when}.", $meta));
                }
            } elseif ($request->has('internal_notes') || $request->has('technician_id')) {
                $meta = ['type' => 'booking_updated', 'visit_id' => $visit->id];

                if ($visit->subscription && $visit->subscription->client) {
                    $visit->subscription->client->notify(new AdminNotification('Visit Updated', "Your visit #{$visit->id} details have been updated.", $meta));
                }
                if ($visit->technician_id) {
                    User::find($visit->technician_id)?->notify(new AdminNotification('Visit Updated', "Visit #{$visit->id} details have been updated.", $meta));
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Failed to send booking detail update notifications: '.$e->getMessage());
        }

        return $this->bookingDetail($id);
    }

    private function computeEndTime(Visit $v): ?string
    {
        if (! $v->scheduled_time) {
            return null;
        }

        $duration = $v->duration_minutes;
        if (! $duration) {
            $slot = JobTimeSlot::where('start_time', $v->scheduled_time)->first();
            $duration = $slot ? (int) $slot->duration_minutes : 60;
        }

        $totalMinutes = (self::toMinutes($v->scheduled_time) + $duration) % (24 * 60);

        return sprintf('%02d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60);
    }

    private static function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private function jobTitleFromNotes(string $notes, int $visitId): string
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));

        return ($parts[0] ?? '') !== '' ? $parts[0] : 'Job #'.$visitId;
    }
}
