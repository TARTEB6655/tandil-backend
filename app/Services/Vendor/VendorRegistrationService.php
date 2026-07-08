<?php

namespace App\Services\Vendor;

use App\Enums\VendorDocumentType;
use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VendorRegistrationService
{
    public function __construct(
        private readonly VendorDocumentService $documents,
        private readonly VendorApplicationService $application
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, UploadedFile|null>  $documentFiles  type => file
     */
    public function register(array $data, ?UploadedFile $logo = null, array $documentFiles = []): Vendor
    {
        $vendor = DB::transaction(function () use ($data, $logo, $documentFiles) {
            $user = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => 'vendor',
                'status' => 'active',
            ]);
            $user->assignRole('vendor');

            $vendor = Vendor::create([
                'user_id' => $user->id,
                'status' => VendorStatus::UnderReview->value,
            ]);

            $logoPath = $logo ? $logo->store('vendors/logos', 'public') : null;

            $profileData = array_merge(
                $this->mapProfileFields($data),
                [
                    'vendor_id' => $vendor->id,
                    'business_name' => $data['business_name'],
                    'owner_name' => $data['owner_name'],
                    'email' => $data['email'],
                    'logo_path' => $logoPath,
                    'onboarding_completed_at' => now(),
                ]
            );

            if ($this->termsAccepted($data)) {
                $profileData['terms_accepted_at'] = now();
            }

            VendorProfile::create($profileData);

            if (! empty($data['category_ids']) && is_array($data['category_ids'])) {
                $this->application->syncCategories($vendor, $data['category_ids']);
            }

            foreach ($documentFiles as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $this->documents->upload($vendor, $type, $file);
                }
            }

            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => null,
                'action' => 'submitted_for_review',
                'old_status' => null,
                'new_status' => VendorStatus::UnderReview->value,
                'notes' => 'Vendor registration submitted for admin review.',
            ]);

            return $vendor->load(['profile', 'user', 'documents', 'categories']);
        });

        app(VendorAdminNotifier::class)->newRegistration($vendor);

        return $vendor;
    }

    /**
     * @param  array<string, UploadedFile|null>  $documentFiles  type => file
     */
    public function updateProfile(Vendor $vendor, array $data, ?UploadedFile $logo = null, bool $removeLogo = false, array $documentFiles = []): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $logo, $removeLogo, $documentFiles) {
            $profile = $vendor->profile;

            // Only persist fields that were actually provided so partial edits don't wipe data.
            $updates = $this->mapProfileFields($data);
            foreach (['business_name', 'owner_name', 'email'] as $core) {
                if (array_key_exists($core, $data) && $data[$core] !== null) {
                    $updates[$core] = $data[$core];
                }
            }

            if ($this->termsAccepted($data) && $profile->terms_accepted_at === null) {
                $updates['terms_accepted_at'] = now();
            }

            if ($removeLogo && $profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
                $updates['logo_path'] = null;
            }
            if ($logo) {
                if ($profile->logo_path) {
                    Storage::disk('public')->delete($profile->logo_path);
                }
                $updates['logo_path'] = $logo->store('vendors/logos', 'public');
            }

            if (! empty($updates)) {
                $profile->update($updates);
            }

            if (isset($data['category_ids']) && is_array($data['category_ids'])) {
                $this->application->syncCategories($vendor, $data['category_ids']);
            }

            if (! empty($data['owner_name'])) {
                $vendor->user->update(['name' => $data['owner_name']]);
            }

            foreach ($documentFiles as $type => $file) {
                if ($file instanceof UploadedFile) {
                    $this->documents->upload($vendor, $type, $file);
                }
            }

            return $vendor->fresh(['profile', 'user', 'documents', 'categories']);
        });
    }

    /**
     * Vendor self-service Edit Profile update (API) — mobile UI fields only.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateEditableProfile(
        Vendor $vendor,
        array $data,
        ?UploadedFile $logo = null
    ): Vendor {
        $data = $this->mapEditProfileInput($data);

        return DB::transaction(function () use ($vendor, $data, $logo) {
            $profile = $vendor->profile;
            $updates = [];

            foreach ([
                'business_name',
                'owner_name',
                'phone',
                'description',
                'address',
                'city',
                'operating_hours',
                'delivery_radius',
                'minimum_order_amount',
                'bank_name',
                'iban',
                'account_holder_name',
            ] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field] === '' ? null : $data[$field];
                }
            }

            if ($logo) {
                if ($profile->logo_path) {
                    Storage::disk('public')->delete($profile->logo_path);
                }
                $updates['logo_path'] = $logo->store('vendors/logos', 'public');
            }

            if (! empty($updates)) {
                $profile->update($updates);
            }

            $userUpdates = [];
            if (array_key_exists('owner_name', $data) && $data['owner_name'] !== null && $data['owner_name'] !== '') {
                $userUpdates['name'] = $data['owner_name'];
            }
            if (array_key_exists('phone', $data)) {
                $userUpdates['phone'] = $data['phone'] === '' ? null : $data['phone'];
            }
            if ($userUpdates !== []) {
                $vendor->user->update($userUpdates);
            }

            return $vendor->fresh(['profile', 'user']);
        });
    }

    /**
     * Map Edit Profile UI field names to database columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapEditProfileInput(array $data): array
    {
        if (array_key_exists('contact_person', $data)) {
            $data['owner_name'] = $data['contact_person'];
            unset($data['contact_person']);
        }

        if (array_key_exists('store_description', $data)) {
            $data['description'] = $data['store_description'];
            unset($data['store_description']);
        }

        if (array_key_exists('delivery_radius_km', $data)) {
            $data['delivery_radius'] = $data['delivery_radius_km'];
            unset($data['delivery_radius_km']);
        }

        $opensAt = trim((string) ($data['opens_at'] ?? ''));
        $closesAt = trim((string) ($data['closes_at'] ?? ''));

        if ($opensAt !== '' && $closesAt !== '') {
            $data['operating_hours'] = $opensAt.' - '.$closesAt;
        }

        unset($data['opens_at'], $data['closes_at']);

        return $data;
    }

    /**
     * Map incoming request data to vendor_profiles columns, keeping only provided keys.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapProfileFields(array $data): array
    {
        $fields = [
            'phone',
            'trade_license_number',
            'vendor_type',
            'emirate',
            'city',
            'address',
            'google_maps_location',
            'bank_name',
            'iban',
            'account_holder_name',
            'delivery_radius',
            'operating_hours',
            'minimum_order_amount',
            'tax_vat_number',
            'description',
            'years_in_business',
        ];

        $mapped = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $mapped[$field] = $data[$field] === '' ? null : $data[$field];
            }
        }

        // Accept "vat_number" as alias for tax_vat_number.
        if (! array_key_exists('tax_vat_number', $mapped) && array_key_exists('vat_number', $data)) {
            $mapped['tax_vat_number'] = $data['vat_number'] === '' ? null : $data['vat_number'];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function termsAccepted(array $data): bool
    {
        if (! array_key_exists('terms_accepted', $data)) {
            return false;
        }

        return filter_var($data['terms_accepted'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Collect document files from request using standard and legacy field names.
     *
     * @return array<string, UploadedFile>
     */
    public static function documentFilesFromRequest(\Illuminate\Http\Request $request): array
    {
        $files = [];
        foreach (VendorDocumentType::values() as $type) {
            if ($request->hasFile($type)) {
                $files[$type] = $request->file($type);
            }
        }
        if ($request->hasFile('business_proof') && ! isset($files[VendorDocumentType::BusinessLicense->value])) {
            $files[VendorDocumentType::BusinessLicense->value] = $request->file('business_proof');
        }

        return $files;
    }
}
