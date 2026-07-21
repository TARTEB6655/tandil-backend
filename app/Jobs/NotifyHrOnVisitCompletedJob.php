<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotification;
use App\Support\RoleUsersNotifier;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Sends HR notifications after technician report submit without blocking the API response.
 */
class NotifyHrOnVisitCompletedJob
{
    use Dispatchable;

    public function __construct(
        public int $visitId,
        public int $technicianUserId,
    ) {}

    public function handle(): void
    {
        $visit = Visit::query()->find($this->visitId);
        $technician = User::query()->find($this->technicianUserId);
        if ($visit === null || $technician === null) {
            return;
        }

        try {
            $hrUsers = RoleUsersNotifier::usersForRole('hr');
            if ($hrUsers->isEmpty()) {
                return;
            }

            $title = 'Visit completed';
            $message = sprintf(
                'Visit #%d was completed by %s.',
                $visit->id,
                $technician->name ?? 'Technician'
            );
            $meta = [
                'type' => 'hr_visit_completed',
                'visit_id' => $visit->id,
                'technician_id' => $technician->id,
                'technician_name' => $technician->name ?? null,
                'completed_at' => now()->toIso8601String(),
            ];

            foreach ($hrUsers as $hr) {
                $hr->notify(new AdminNotification($title, $message, $meta));
            }
        } catch (\Throwable $e) {
            Log::warning('NotifyHrOnVisitCompletedJob failed', [
                'visit_id' => $this->visitId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
