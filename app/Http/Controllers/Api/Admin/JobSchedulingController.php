<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\JobBlockedDate;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Visit;
use App\Services\JobSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Admin "Job Scheduling" screens: Working hours & capacity, Time slots,
 * Blocked dates & slots, Jobs calendar.
 */
class JobSchedulingController extends Controller
{
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
     */
    public function updateWorkingHours(Request $request)
    {
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
     * Jobs calendar with technician-overlap flag per job.
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
            ->with(['technician:id,name', 'subscription.client:id,name'])
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();

        // Technician overlap: same technician + same date + overlapping time windows.
        $overlappingIds = [];
        $byTechnicianDate = $visits->filter(fn (Visit $v) => $v->technician_id && $v->scheduled_time)
            ->groupBy(fn (Visit $v) => $v->technician_id.'|'.$v->scheduled_date->toDateString());
        foreach ($byTechnicianDate as $group) {
            if ($group->count() < 2) {
                continue;
            }
            foreach ($group as $v) {
                if (JobSchedulingService::hasTechnicianConflict((int) $v->technician_id, $v->scheduled_date->toDateString(), $v->scheduled_time, (int) $v->id, $v->duration_minutes)) {
                    $overlappingIds[$v->id] = true;
                }
            }
        }

        $jobs = $visits->map(function (Visit $v) use ($overlappingIds) {
            return [
                'id' => $v->id,
                'scheduled_date' => $v->scheduled_date?->toDateString(),
                'scheduled_time' => $v->scheduled_time,
                'end_time' => $this->computeEndTime($v),
                'status' => $v->status,
                'notes' => $v->notes,
                'technician' => $v->technician ? ['id' => $v->technician->id, 'name' => $v->technician->name] : null,
                'client' => $v->subscription?->client ? ['id' => $v->subscription->client->id, 'name' => $v->subscription->client->name] : null,
                'technician_overlap' => isset($overlappingIds[$v->id]),
            ];
        })->values();

        return ApiResponse::success('Jobs calendar retrieved successfully.', [
            'view' => $view,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total' => $jobs->count(),
            'overlap_count' => count($overlappingIds),
            'jobs' => $jobs,
        ]);
    }

    /**
     * GET /api/admin/job-scheduling/jobs/{id}
     * Booking detail screen: tap a job on the Jobs calendar. Editing/saving
     * uses the existing PUT /api/visits/{id} (Update Visit) — both "Save
     * changes" and "Reschedule & notify" map to that same endpoint; it already
     * validates the slot/technician conflict and fires the reschedule/update
     * notification to the client + technician.
     */
    public function bookingDetail(int $id)
    {
        $visit = Visit::with(['technician:id,name', 'subscription.client:id,name'])->find($id);
        if (! $visit) {
            return ApiResponse::error('Job not found.', 404);
        }

        $endTime = $this->computeEndTime($visit);
        $scheduledDate = $visit->scheduled_date?->toDateString();
        $currentScheduleLabel = $scheduledDate
            ? Carbon::parse($scheduledDate)->format('D, M j').($visit->scheduled_time ? ' · '.$visit->scheduled_time.($endTime ? '–'.$endTime : '') : '')
            : null;

        $overlap = ($visit->technician_id && $visit->scheduled_time)
            ? JobSchedulingService::hasTechnicianConflict((int) $visit->technician_id, $scheduledDate, $visit->scheduled_time, $visit->id, $visit->duration_minutes)
            : false;

        return ApiResponse::success('Booking detail retrieved successfully.', [
            'id' => $visit->id,
            'title' => $this->jobTitleFromNotes((string) $visit->notes, $visit->id),
            'client' => $visit->subscription?->client ? ['id' => $visit->subscription->client->id, 'name' => $visit->subscription->client->name] : null,
            'technician' => $visit->technician ? ['id' => $visit->technician->id, 'name' => $visit->technician->name] : null,
            'scheduled_date' => $scheduledDate,
            'scheduled_time' => $visit->scheduled_time,
            'end_time' => $endTime,
            'duration_minutes' => $visit->duration_minutes,
            'current_schedule_label' => $currentScheduleLabel ? 'Currently '.$currentScheduleLabel : null,
            'status' => $visit->status,
            'notes' => $visit->notes,
            'technician_overlap' => $overlap,
        ]);
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
