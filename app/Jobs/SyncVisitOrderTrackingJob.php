<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Support\VisitOrderTrackingSync;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Defers order-status sync so visit create/update APIs return faster.
 * Uses afterResponse() — no queue worker required.
 */
class SyncVisitOrderTrackingJob
{
    use Dispatchable;

    public function __construct(
        public int $visitId,
    ) {}

    public function handle(): void
    {
        $visit = Visit::query()->find($this->visitId);
        if ($visit === null) {
            return;
        }

        try {
            VisitOrderTrackingSync::syncFromVisit($visit);
        } catch (\Throwable $e) {
            Log::warning('SyncVisitOrderTrackingJob failed', [
                'visit_id' => $this->visitId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
