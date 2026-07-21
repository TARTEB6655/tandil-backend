<?php

namespace App\Services;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Str;

class HrVisitAssignmentService
{
    /**
     * @return array{success: bool, message?: string, data?: array, accept_by?: string, errors?: array}
     */
    public static function assignVisit(int $visitId, int $technicianUserId, ?string $scheduledDateInput, ?string $note): array
    {
        $visit = self::editableQuery()->find($visitId);
        if (! $visit) {
            return ['success' => false, 'message' => 'Visit not found or not assignable.'];
        }

        if ($visit->status === 'pending_acceptance' && $visit->accept_by && Carbon::parse($visit->accept_by)->isFuture()) {
            return [
                'success' => false,
                'message' => 'This job is already offered to a technician. Wait for accept/reject or expiry before reassigning.',
            ];
        }

        $technician = User::role('technician')->active()->find($technicianUserId);
        if (! $technician) {
            return ['success' => false, 'message' => 'Technician not found or inactive.'];
        }

        $scheduledDate = $visit->scheduled_date?->toDateString() ?? $scheduledDateInput ?? Carbon::today()->toDateString();
        if (self::isTechnicianOnLeave($technician->id, $scheduledDate)) {
            return ['success' => false, 'message' => 'Technician is on approved leave for this date.'];
        }

        $supId = HrTechnicianMonthlyReportService::resolveSupervisorIdForVisit($visit->area_id ? (int) $visit->area_id : null);
        if ($supId) {
            $visit->supervisor_id = $supId;
        }
        if (! $visit->area_id) {
            $firstArea = $technician->assignedAreas()->value('areas.id');
            if ($firstArea) {
                $visit->area_id = $firstArea;
                if (! $visit->supervisor_id) {
                    $visit->supervisor_id = HrTechnicianMonthlyReportService::resolveSupervisorIdForVisit((int) $firstArea);
                }
            }
        }

        $visit->escalated_at = null;
        $visit->offer_count = 0;
        if ($scheduledDateInput) {
            $visit->scheduled_date = $scheduledDateInput;
        }
        if ($note !== null && $note !== '') {
            $visit->notes = trim(($visit->notes ? $visit->notes . PHP_EOL : '') . $note);
        }

        VisitOfferService::offerToTechnician($visit, $technician->id);
        $visit->load(['technician', 'supervisor', 'area', 'subscription.client']);

        return [
            'success' => true,
            'message' => 'Job offered to technician. They have ' . VisitOfferService::ACCEPT_MINUTES . ' minutes to accept.',
            'data' => self::mapVisit($visit),
            'accept_by' => $visit->accept_by?->toIso8601String(),
        ];
    }

    public static function editableQuery()
    {
        return Visit::query()->where(function ($q) {
            $q->whereNull('technician_id')
                ->orWhereIn('status', ['pending', 'scheduled', 'pending_acceptance', 'in_progress'])
                ->orWhereNotNull('escalated_at');
        });
    }

    public static function assignableQuery()
    {
        return Visit::query()->where(function ($q) {
            $q->whereNull('technician_id')
                ->orWhereIn('status', ['pending', 'scheduled'])
                ->orWhereNotNull('escalated_at');
        });
    }

    public static function isTechnicianOnLeave(int $userId, ?string $date): bool
    {
        if (! $date) {
            return false;
        }

        return LeaveRequest::where('user_id', $userId)
            ->whereRaw('LOWER(status) = ?', ['approved'])
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    public static function mapVisit(Visit $visit): array
    {
        $visit->loadMissing(['supervisor', 'subscription.client', 'area', 'technician']);
        $meta = self::parseVisitMetaFromNotes((string) ($visit->notes ?? ''));
        $client = $visit->subscription?->client;

        return [
            'id' => $visit->id,
            'scheduled_date' => $visit->scheduled_date?->format('Y-m-d'),
            'status' => $visit->status,
            'accept_by' => $visit->accept_by?->toIso8601String(),
            'title' => $meta['farm_name'] ?? ('Task #' . $visit->id),
            'service_name' => $meta['service_name'] ?? null,
            'location' => $meta['location'] ?? null,
            'price' => $visit->price !== null ? (float) $visit->price : null,
            'notes_preview' => Str::limit((string) ($visit->notes ?? ''), 120),
            'client' => $client ? ['id' => $client->id, 'name' => $client->name] : null,
            'area' => $visit->area ? ['id' => $visit->area->id, 'name' => $visit->area->name] : null,
            'supervisor' => $visit->supervisor ? ['id' => $visit->supervisor->id, 'name' => $visit->supervisor->name] : null,
            'technician' => $visit->technician ? ['id' => $visit->technician->id, 'name' => $visit->technician->name] : null,
            'flags' => [
                'is_unassigned' => $visit->technician_id === null,
                'is_escalated' => $visit->escalated_at !== null,
                'is_pending_acceptance' => $visit->status === 'pending_acceptance',
            ],
        ];
    }

    public static function parseVisitMetaFromNotes(string $notes): array
    {
        $clean = trim(preg_replace('/^\[DUMMY-SUP-ASSIGN\]\s*/', '', $notes) ?? $notes);
        $parts = array_values(array_filter(array_map('trim', explode('|', $clean)), fn ($p) => $p !== ''));
        $farm = $parts[0] ?? null;
        $service = isset($parts[1]) ? trim($parts[1]) : null;
        if ($service && preg_match('/^(.+?)\s+Visit\s*$/i', $service, $m)) {
            $service = trim($m[1]);
        }
        $location = isset($parts[2]) ? trim($parts[2]) : null;
        $duration_minutes = null;
        if (isset($parts[3]) && preg_match('/(\d+)\s*min/i', $parts[3], $m)) {
            $duration_minutes = (int) $m[1];
        }
        $price_display = isset($parts[4]) ? trim($parts[4]) : null;

        return [
            'farm_name' => $farm ?: null,
            'service_name' => $service ?: null,
            'location' => $location ?: null,
            'duration_minutes' => $duration_minutes,
            'price_display' => $price_display ?: null,
        ];
    }
}
