<?php

namespace App\Services\Vendor;

use App\Enums\VendorDocumentType;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use Illuminate\Support\Collection;

class VendorApplicationService
{
    public function __construct(
        private readonly VendorApprovalService $approval
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function applicationPayload(Vendor $vendor): array
    {
        $vendor->loadMissing(['profile', 'documents', 'categories', 'approvalLogs.performer']);

        $required = collect(VendorDocumentType::requiredForOnboarding())->map(function (VendorDocumentType $type) use ($vendor) {
            $doc = $vendor->documents->firstWhere('type', $type->value);

            return [
                'type' => $type->value,
                'label' => $type->label(),
                'uploaded' => $doc !== null,
                'verification_status' => $doc?->verification_status,
                'file_url' => $doc?->file_url,
                'document_id' => $doc?->id,
            ];
        })->values()->all();

        $profileComplete = $this->isProfileComplete($vendor);
        $documentsComplete = $this->missingRequiredDocuments($vendor)->isEmpty();
        $categoriesComplete = $vendor->categories->isNotEmpty();

        return [
            'vendor_id' => $vendor->id,
            'status' => $vendor->status,
            'status_label' => $vendor->statusEnum()->label(),
            'rejection_reason' => $vendor->rejection_reason,
            'can_sell' => $vendor->isApproved(),
            'can_edit_application' => $vendor->statusEnum()->canCompleteOnboarding(),
            'can_resubmit' => $vendor->status === VendorStatus::Rejected->value,
            'profile_complete' => $profileComplete,
            'documents_complete' => $documentsComplete,
            'categories_complete' => $categoriesComplete,
            'terms_accepted' => $vendor->profile?->terms_accepted_at !== null,
            'missing_profile_fields' => $this->missingProfileFields($vendor)->values()->all(),
            'onboarding_complete' => $profileComplete && $documentsComplete && $categoriesComplete,
            'onboarding_completed_at' => $vendor->profile?->onboarding_completed_at?->toIso8601String(),
            'required_documents' => $required,
            'missing_document_types' => $this->missingRequiredDocuments($vendor)->map(fn ($t) => $t->value)->values()->all(),
            'category_ids' => $vendor->categories->pluck('id')->values()->all(),
            'completion_percent' => $this->completionPercent($vendor),
            'approval_logs' => $vendor->approvalLogs->take(30)->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'old_status' => $log->old_status,
                'new_status' => $log->new_status,
                'notes' => $log->notes,
                'performed_by' => $log->performer?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ];
    }

    /**
     * Required Business Profile fields (column => human label) that must be filled
     * before the vendor can submit the application for approval.
     *
     * @return array<string, string>
     */
    public static function requiredProfileFields(): array
    {
        return [
            'business_name' => 'Company Name',
            'owner_name' => 'Authorized Person Name',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'trade_license_number' => 'Trade License Number',
            'address' => 'Address',
            'vendor_type' => 'Vendor Type',
            'emirate' => 'Emirate',
            'google_maps_location' => 'Google Maps Location',
            'bank_name' => 'Bank Name',
            'iban' => 'IBAN',
            'account_holder_name' => 'Account Holder Name',
            'delivery_radius' => 'Delivery Radius',
            'operating_hours' => 'Operating Hours',
            'minimum_order_amount' => 'Minimum Order Amount',
            'logo_path' => 'Company Logo',
        ];
    }

    public function isProfileComplete(Vendor $vendor): bool
    {
        return $this->missingProfileFields($vendor)->isEmpty()
            && $vendor->profile?->terms_accepted_at !== null;
    }

    /**
     * Required profile fields that are still empty.
     *
     * @return Collection<int, string> list of human labels
     */
    public function missingProfileFields(Vendor $vendor): Collection
    {
        $p = $vendor->profile;
        if ($p === null) {
            return collect(self::requiredProfileFields())->values();
        }

        return collect(self::requiredProfileFields())
            ->filter(fn (string $label, string $field) => blank($p->{$field}))
            ->values();
    }

    /** @return Collection<int, VendorDocumentType> */
    public function missingRequiredDocuments(Vendor $vendor): Collection
    {
        $uploaded = $vendor->documents->pluck('type')->all();

        return collect(VendorDocumentType::requiredForOnboarding())
            ->filter(fn (VendorDocumentType $type) => ! in_array($type->value, $uploaded, true));
    }

    public function completionPercent(Vendor $vendor): int
    {
        $requiredFields = self::requiredProfileFields();
        $requiredDocs = VendorDocumentType::requiredForOnboarding();

        // Total checklist items: each profile field + terms acceptance + each required document + categories.
        $total = count($requiredFields) + 1 + count($requiredDocs) + 1;
        if ($total === 0) {
            return 0;
        }

        $done = count($requiredFields) - $this->missingProfileFields($vendor)->count();
        if ($vendor->profile?->terms_accepted_at !== null) {
            $done++;
        }
        $done += count($requiredDocs) - $this->missingRequiredDocuments($vendor)->count();
        if ($vendor->categories->isNotEmpty()) {
            $done++;
        }

        return (int) round((max(0, $done) / $total) * 100);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    public function syncCategories(Vendor $vendor, array $categoryIds): void
    {
        $ids = Category::query()->whereIn('id', $categoryIds)->where('is_active', true)->pluck('id')->all();
        $vendor->categories()->sync($ids);
    }

    public function markOnboardingSubmitted(Vendor $vendor): Vendor
    {
        $previousStatus = $vendor->status;
        $vendor->profile?->update(['onboarding_completed_at' => now()]);

        if ($vendor->status === VendorStatus::Rejected->value) {
            return $this->approval->transition(
                $vendor,
                VendorStatus::Pending,
                null,
                'Application resubmitted by vendor.'
            );
        }

        if ($vendor->status === VendorStatus::Pending->value) {
            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => $vendor->user_id,
                'action' => 'onboarding_submitted',
                'old_status' => $vendor->status,
                'new_status' => $vendor->status,
                'notes' => 'Vendor completed onboarding checklist.',
            ]);
        }

        $vendor = $vendor->fresh(['profile', 'documents', 'categories']);

        if ($previousStatus === VendorStatus::Pending->value) {
            app(VendorAdminNotifier::class)->applicationSubmitted($vendor);
        }

        return $vendor;
    }

    public function resubmit(Vendor $vendor): Vendor
    {
        if ($this->missingRequiredDocuments($vendor)->isNotEmpty()) {
            throw new \InvalidArgumentException('Upload all required documents before resubmitting.');
        }

        if (! $this->isProfileComplete($vendor)) {
            throw new \InvalidArgumentException('Complete your business profile before resubmitting.');
        }

        if ($vendor->categories()->count() === 0) {
            throw new \InvalidArgumentException('Select at least one business category.');
        }

        $vendor->profile?->update(['onboarding_completed_at' => now()]);

        $vendor = $this->approval->transition(
            $vendor,
            VendorStatus::Pending,
            null,
            'Application resubmitted by vendor after rejection.'
        );

        app(VendorAdminNotifier::class)->applicationResubmitted($vendor);

        return $vendor;
    }
}
