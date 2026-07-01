<?php

namespace App\Services\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Notifications\AdminNotification;
use Illuminate\Support\Facades\Log;

class VendorAdminNotifier
{
    public function newRegistration(Vendor $vendor): void
    {
        $vendor->loadMissing('profile');
        $label = $this->vendorLabel($vendor);

        $this->notifyAdmins(
            'New Vendor Registration',
            "{$label} signed up and is awaiting review. Review their profile, documents, and business details.",
            $vendor,
            'new_registration'
        );
    }

    public function applicationSubmitted(Vendor $vendor): void
    {
        $vendor->loadMissing('profile');
        $label = $this->vendorLabel($vendor);

        $this->notifyAdmins(
            'Vendor Application Submitted',
            "{$label} completed onboarding and submitted their application for approval.",
            $vendor,
            'application_submitted'
        );
    }

    public function applicationResubmitted(Vendor $vendor): void
    {
        $vendor->loadMissing('profile');
        $label = $this->vendorLabel($vendor);

        $this->notifyAdmins(
            'Vendor Application Resubmitted',
            "{$label} updated their application after rejection and resubmitted for review.",
            $vendor,
            'application_resubmitted'
        );
    }

    private function vendorLabel(Vendor $vendor): string
    {
        return $vendor->profile?->business_name
            ?? $vendor->profile?->owner_name
            ?? 'Vendor #'.$vendor->id;
    }

    private function notifyAdmins(string $title, string $message, Vendor $vendor, string $action): void
    {
        try {
            $meta = [
                'entity' => 'vendor',
                'vendor_id' => $vendor->id,
                'action' => $action,
                'business_name' => $vendor->profile?->business_name,
                'owner_name' => $vendor->profile?->owner_name,
                'status' => $vendor->status,
            ];

            $admins = User::role('admin')->get();
            if ($admins->isEmpty()) {
                Log::warning('Vendor admin notification skipped: no admin users found.', [
                    'vendor_id' => $vendor->id,
                    'action' => $action,
                ]);

                return;
            }

            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification($title, $message, $meta));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to notify admins about vendor event: '.$e->getMessage(), [
                'vendor_id' => $vendor->id,
                'action' => $action,
            ]);
        }
    }
}
