<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use App\Notifications\VendorApplicationStatusNotification;
use Illuminate\Support\Facades\Log;

class VendorVendorNotifier
{
    public function approved(Vendor $vendor, ?string $notes = null): void
    {
        $this->notifyVendor($vendor, 'approved', null, $notes);
    }

    public function rejected(Vendor $vendor, string $reason, ?string $notes = null): void
    {
        $this->notifyVendor($vendor, 'rejected', $reason, $notes);
    }

    public function registrationSubmitted(Vendor $vendor): void
    {
        $this->notifyVendor($vendor, 'submitted');
    }

    public function missingDocuments(Vendor $vendor, ?string $notes = null): void
    {
        $this->notifyVendor($vendor, 'missing_documents', null, $notes);
    }

    private function notifyVendor(Vendor $vendor, string $status, ?string $reason = null, ?string $notes = null): void
    {
        try {
            $user = $vendor->user;
            if ($user === null) {
                return;
            }

            $user->notify(new VendorApplicationStatusNotification($vendor, $status, $reason, $notes));
        } catch (\Throwable $e) {
            Log::error('Failed to notify vendor about application status: '.$e->getMessage(), [
                'vendor_id' => $vendor->id,
                'status' => $status,
            ]);
        }
    }
}
