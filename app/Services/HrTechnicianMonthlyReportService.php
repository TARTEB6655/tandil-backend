<?php

namespace App\Services;

use App\Models\Area;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Visit;
use App\Services\ProfilePictureUploadService;
use Carbon\Carbon;

class HrTechnicianMonthlyReportService
{
    /**
     * Full JSON payload for HR: technician profile, month window, leave, visits, working metrics.
     */
    public static function buildPayload(int $technicianId, int $year, int $month): array
    {
        $tech = User::role('technician')->with('employee')->find($technicianId);
        if (! $tech) {
            return [];
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $calendarDaysInMonth = (int) $start->daysInMonth;

        $leaves = LeaveRequest::where('user_id', $technicianId)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->orderBy('start_date')
            ->get();

        $leaveSegments = [];
        $approvedLeaveDaysInMonth = 0;
        foreach ($leaves as $lr) {
            $overlap = self::overlapDaysWithRange($lr->start_date, $lr->end_date, $start, $end);
            $row = [
                'id' => $lr->id,
                'leave_type' => $lr->leave_type,
                'start_date' => $lr->start_date->format('Y-m-d'),
                'end_date' => $lr->end_date->format('Y-m-d'),
                'status' => $lr->status,
                'days_overlapping_month' => $overlap,
                'reason' => $lr->reason,
            ];
            $leaveSegments[] = $row;
            if (strtolower((string) $lr->status) === 'approved') {
                $approvedLeaveDaysInMonth += $overlap;
            }
        }

        $visits = Visit::query()
            ->where('technician_id', $technicianId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('scheduled_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->whereNotNull('completed_at')
                            ->whereDate('completed_at', '>=', $startDate)
                            ->whereDate('completed_at', '<=', $endDate);
                    });
            })
            ->with(['subscription.client', 'area', 'supervisor'])
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        $visitRows = $visits->map(function (Visit $v) use ($startDate, $endDate) {
            $client = $v->subscription?->client;
            $meta = self::parseVisitMetaFromNotes((string) ($v->notes ?? ''));

            return [
                'id' => $v->id,
                'scheduled_date' => $v->scheduled_date?->format('Y-m-d'),
                'status' => $v->status,
                'completed_at' => $v->completed_at?->toIso8601String(),
                'completed_date' => $v->completed_date?->format('Y-m-d'),
                'price' => $v->price !== null ? (float) $v->price : null,
                'title' => $meta['farm_name'] ?? ('Visit #' . $v->id),
                'service_name' => $meta['service_name'] ?? null,
                'location' => $meta['location'] ?? null,
                'client' => $client ? [
                    'id' => $client->id,
                    'name' => $client->name,
                ] : null,
                'area' => $v->area ? ['id' => $v->area->id, 'name' => $v->area->name] : null,
                'supervisor' => $v->supervisor ? ['id' => $v->supervisor->id, 'name' => $v->supervisor->name] : null,
                'is_completed_in_month' => in_array($v->status, ['completed', 'approved'], true)
                    && $v->completed_at
                    && ($cd = Carbon::parse($v->completed_at)->toDateString()) >= $startDate
                    && $cd <= $endDate,
            ];
        })->values()->all();

        $completedInMonth = $visits->filter(function (Visit $v) use ($startDate, $endDate) {
            if (! in_array($v->status, ['completed', 'approved'], true) || ! $v->completed_at) {
                return false;
            }
            $cd = Carbon::parse($v->completed_at)->toDateString();

            return $cd >= $startDate && $cd <= $endDate;
        });

        $distinctWorkDates = $completedInMonth
            ->map(fn (Visit $v) => Carbon::parse($v->completed_at)->toDateString())
            ->unique()
            ->sort()
            ->values()
            ->all();

        $initial = mb_substr(trim((string) $tech->name), 0, 1) ?: '?';

        return [
            'technician' => [
                'id' => $tech->id,
                'name' => $tech->name,
                'email' => $tech->email,
                'phone' => $tech->phone ?? $tech->employee?->phone,
                'employee_id' => $tech->employee?->employee_id ?? ('TECH-' . $tech->id),
                'profile_picture_url' => ProfilePictureUploadService::fullUrlOrDefault($tech->profile_picture ?? null, $initial),
            ],
            'period' => [
                'year' => $year,
                'month' => $month,
                'month_label' => $start->format('F Y'),
                'date_from' => $startDate,
                'date_to' => $endDate,
            ],
            'summary' => [
                'calendar_days_in_month' => $calendarDaysInMonth,
                'approved_leave_days_in_month' => $approvedLeaveDaysInMonth,
                'estimated_working_days' => max(0, $calendarDaysInMonth - $approvedLeaveDaysInMonth),
                'days_with_completed_job' => count($distinctWorkDates),
                'jobs_completed_in_month' => $completedInMonth->count(),
                'jobs_scheduled_in_month' => $visits->count(),
            ],
            'leave_requests' => $leaveSegments,
            'completed_visit_dates' => $distinctWorkDates,
            'visits' => $visitRows,
        ];
    }

    public static function buildPlainText(int $technicianId, int $year, int $month, ?string $title = null): string
    {
        $data = self::buildPayload($technicianId, $year, $month);
        if ($data === []) {
            return "Technician not found (ID: {$technicianId}).\n";
        }
        $t = $data['technician'];
        $p = $data['period'];
        $s = $data['summary'];
        $lines = [
            'HR TECHNICIAN MONTHLY REPORT',
            '',
            'Report: ' . ($title ?: ($t['name'] . ' — ' . $p['month_label'])),
            'Period: ' . $p['date_from'] . ' to ' . $p['date_to'],
            'Generated at: ' . now()->toDateTimeString(),
            '---',
            '',
            'Technician',
            '  Name: ' . $t['name'],
            '  Employee ID: ' . $t['employee_id'],
            '  Email: ' . ($t['email'] ?? '—'),
            '  Phone: ' . ($t['phone'] ?? '—'),
            '',
            'Summary',
            '  Calendar days in month: ' . $s['calendar_days_in_month'],
            '  Approved leave days (in this month): ' . $s['approved_leave_days_in_month'],
            '  Estimated working days (calendar − leave): ' . $s['estimated_working_days'],
            '  Days with ≥1 completed job: ' . $s['days_with_completed_job'],
            '  Jobs completed in month: ' . $s['jobs_completed_in_month'],
            '  Visits in scope (scheduled or completed in month): ' . $s['jobs_scheduled_in_month'],
            '',
            '---',
            'Leave (overlapping month)',
            '',
        ];
        foreach ($data['leave_requests'] as $lr) {
            $lines[] = '  • ' . $lr['leave_type'] . ' | ' . $lr['start_date'] . ' → ' . $lr['end_date'] . ' | ' . $lr['status'] . ' | days in month: ' . $lr['days_overlapping_month'];
            if (! empty($lr['reason'])) {
                $lines[] = '    Reason: ' . preg_replace("/\s+/", ' ', (string) $lr['reason']);
            }
        }
        if (count($data['leave_requests']) === 0) {
            $lines[] = '  (none)';
        }
        $lines[] = '';
        $lines[] = '---';
        $lines[] = 'Visits / jobs (scheduled or completed in this month)';
        $lines[] = '';
        foreach ($data['visits'] as $v) {
            $client = $v['client']['name'] ?? '—';
            $lines[] = 'Visit #' . $v['id'] . ' | ' . ($v['title'] ?? '') . ' | scheduled ' . ($v['scheduled_date'] ?? '—') . ' | status ' . ($v['status'] ?? '—') . ' | client: ' . $client;
            if (! empty($v['completed_at'])) {
                $lines[] = '  Completed: ' . $v['completed_at'];
            }
            $lines[] = '';
        }
        if (count($data['visits']) === 0) {
            $lines[] = '  (no visits in this period)';
        }

        return implode("\n", $lines);
    }

    public static function resolveSupervisorIdForVisit(?int $areaId): ?int
    {
        if (! $areaId) {
            return null;
        }

        return Area::query()->whereKey($areaId)->first()?->supervisors()->first()?->id;
    }

    private static function overlapDaysWithRange($leaveStart, $leaveEnd, Carbon $rangeStart, Carbon $rangeEnd): int
    {
        $ls = Carbon::parse($leaveStart)->startOfDay();
        $le = Carbon::parse($leaveEnd)->endOfDay();
        $a = $ls->max($rangeStart);
        $b = $le->min($rangeEnd);
        if ($a->gt($b)) {
            return 0;
        }

        return $a->diffInDays($b) + 1;
    }

    private static function parseVisitMetaFromNotes(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $farm = $parts[0] ?? null;
        $service = isset($parts[1]) ? trim($parts[1]) : null;
        if ($service && preg_match('/^(.+?)\s+Visit\s*$/i', $service, $m)) {
            $service = trim($m[1]);
        }
        $location = isset($parts[2]) ? trim($parts[2]) : null;

        return [
            'farm_name' => $farm ?: null,
            'service_name' => $service ?: null,
            'location' => $location ?: null,
        ];
    }
}
