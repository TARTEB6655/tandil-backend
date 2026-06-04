<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use Illuminate\Support\Facades\DB;

class VendorApprovalService
{
    public function transition(Vendor $vendor, VendorStatus $newStatus, ?User $admin, ?string $notes = null, ?string $rejectionReason = null): Vendor
    {
        return DB::transaction(function () use ($vendor, $newStatus, $admin, $notes, $rejectionReason) {
            $old = $vendor->status;
            $vendor->status = $newStatus->value;
            $vendor->rejection_reason = null;

            match ($newStatus) {
                VendorStatus::Approved => $vendor->fill([
                    'approved_at' => now(),
                    'rejected_at' => null,
                    'suspended_at' => null,
                ]),
                VendorStatus::Rejected => $vendor->fill([
                    'rejected_at' => now(),
                    'rejection_reason' => $rejectionReason,
                ]),
                VendorStatus::Suspended => $vendor->fill(['suspended_at' => now()]),
                VendorStatus::Pending => $vendor->fill([
                    'approved_at' => null,
                    'rejected_at' => null,
                    'suspended_at' => null,
                ]),
            };

            $vendor->save();

            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => $admin?->id,
                'action' => $newStatus->value,
                'old_status' => $old,
                'new_status' => $newStatus->value,
                'notes' => $notes,
            ]);

            return $vendor->fresh(['profile', 'user', 'approvalLogs']);
        });
    }

    public function approve(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Approved, $admin, $notes);
    }

    public function reject(Vendor $vendor, User $admin, string $reason, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Rejected, $admin, $notes, $reason);
    }

    public function suspend(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Suspended, $admin, $notes);
    }

    public function activate(Vendor $vendor, User $admin, ?string $notes = null): Vendor
    {
        return $this->transition($vendor, VendorStatus::Approved, $admin, $notes ?? 'Reactivated by admin.');
    }

    /**
     * Permanently remove vendor account, profile, documents, and linked user.
     */
    public function permanentlyDelete(Vendor $vendor, User $admin, ?string $notes = null): void
    {
        DB::transaction(function () use ($vendor, $admin, $notes) {
            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => $admin->id,
                'action' => 'deleted',
                'old_status' => $vendor->status,
                'new_status' => 'deleted',
                'notes' => $notes ?? 'Permanently deleted by admin.',
            ]);

            $user = $vendor->user;
            $vendor->documents()->each(function ($doc) {
                app(VendorDocumentService::class)->delete($doc);
            });
            $vendor->forceDelete();
            if ($user) {
                $user->tokens()->delete();
                $user->delete();
            }
        });
    }
}
