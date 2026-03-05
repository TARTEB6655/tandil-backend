<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitOffer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Job offer flow: find next technician (same zone, same specialization) or escalate to supervisor.
 */
class VisitOfferService
{
    /** Max offers before escalating to supervisor. */
    public const MAX_OFFERS_BEFORE_ESCALATE = 3;

    /** Default minutes for technician to accept. */
    public const ACCEPT_MINUTES = 15;

    /**
     * Get service type from visit (for matching technician specializations).
     * Uses subscription plan or notes.
     */
    public static function getServiceTypeForVisit(Visit $visit): ?string
    {
        $visit->loadMissing('subscription');
        $plan = $visit->subscription?->plan;
        if ($plan) {
            return is_string($plan) ? str_replace('_', ' ', $plan) : null;
        }
        return null;
    }

    /**
     * Find next available technician: same zone (area_id), same specialization if possible, not already offered.
     */
    public static function findNextTechnician(Visit $visit): ?User
    {
        $areaId = $visit->area_id;
        if (! $areaId) {
            return null;
        }

        $alreadyOfferedIds = VisitOffer::where('visit_id', $visit->id)->pluck('technician_id')->toArray();
        $serviceType = self::getServiceTypeForVisit($visit);

        $query = User::role('technician')
            ->whereHas('assignedAreas', fn ($q) => $q->where('areas.id', $areaId))
            ->whereNotIn('id', $alreadyOfferedIds)
            ->with('employee');

        $candidates = $query->orderBy('name')->get();
        if ($candidates->isEmpty()) {
            return null;
        }

        if ($serviceType !== null && $serviceType !== '') {
            $serviceLower = strtolower($serviceType);
            foreach ($candidates as $tech) {
                $specs = $tech->employee?->specializations ?? [];
                if (is_array($specs)) {
                    foreach ($specs as $s) {
                        if (stripos((string) $s, $serviceLower) !== false || stripos($serviceLower, (string) $s) !== false) {
                            return $tech;
                        }
                    }
                }
            }
        }

        return $candidates->first();
    }

    /**
     * Create an offer to the given technician and set visit state.
     */
    public static function offerToTechnician(Visit $visit, int $technicianId): void
    {
        $acceptBy = Carbon::now()->addMinutes(self::ACCEPT_MINUTES);

        VisitOffer::create([
            'visit_id' => $visit->id,
            'technician_id' => $technicianId,
            'offered_at' => now(),
            'accept_by' => $acceptBy,
            'status' => VisitOffer::STATUS_PENDING,
        ]);

        $visit->technician_id = $technicianId;
        $visit->accept_by = $acceptBy;
        $visit->status = 'pending_acceptance';
        $visit->offer_count = ($visit->offer_count ?? 0) + 1;
        $visit->save();
    }

    /**
     * Mark current offer as accepted and clear accept_by.
     */
    public static function markAccepted(Visit $visit): void
    {
        VisitOffer::where('visit_id', $visit->id)
            ->where('technician_id', $visit->technician_id)
            ->where('status', VisitOffer::STATUS_PENDING)
            ->update(['status' => VisitOffer::STATUS_ACCEPTED]);

        $visit->accepted_at = $visit->accepted_at ?? now();
        $visit->accept_by = null;
        $visit->status = 'in_progress';
        $visit->save();
    }

    /**
     * Mark current offer as rejected and either offer to next technician or escalate.
     */
    public static function markRejectedAndOfferNextOrEscalate(Visit $visit, ?string $reason = null): void
    {
        VisitOffer::where('visit_id', $visit->id)
            ->where('technician_id', $visit->technician_id)
            ->where('status', VisitOffer::STATUS_PENDING)
            ->update([
                'status' => VisitOffer::STATUS_REJECTED,
                'reject_reason' => $reason,
            ]);

        self::offerNextOrEscalate($visit);
    }

    /**
     * Mark current offer as timeout and either offer to next technician or escalate.
     */
    public static function markTimeoutAndOfferNextOrEscalate(Visit $visit): void
    {
        VisitOffer::where('visit_id', $visit->id)
            ->where('technician_id', $visit->technician_id)
            ->where('status', VisitOffer::STATUS_PENDING)
            ->update(['status' => VisitOffer::STATUS_TIMEOUT]);

        self::offerNextOrEscalate($visit);
    }

    private static function offerNextOrEscalate(Visit $visit): void
    {
        if ($visit->offer_count >= self::MAX_OFFERS_BEFORE_ESCALATE) {
            $visit->technician_id = null;
            $visit->accept_by = null;
            $visit->status = 'pending';
            $visit->escalated_at = now();
            $visit->save();
            return;
        }

        $next = self::findNextTechnician($visit);
        if ($next) {
            self::offerToTechnician($visit, $next->id);
        } else {
            $visit->technician_id = null;
            $visit->accept_by = null;
            $visit->status = 'pending';
            $visit->escalated_at = now();
            $visit->save();
        }
    }
}
