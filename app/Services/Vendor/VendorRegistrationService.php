<?php

namespace App\Services\Vendor;

use App\Enums\VendorDocumentType;
use App\Enums\VendorStatus;
use App\Jobs\OptimizePublicDiskImageJob;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorDocument;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class VendorRegistrationService
{
    public const REGISTRATION_SUCCESS_MESSAGE = 'Thank you! We have received your registration request successfully. We will contact you shortly after we review your request.';

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
        // Keep the DB transaction short — no disk I/O or admin fan-out inside it.
        $vendor = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => 'vendor',
                'status' => 'active',
            ]);
            $this->ensureVendorRole($user);

            $vendor = Vendor::create([
                'user_id' => $user->id,
                'status' => VendorStatus::UnderReview->value,
            ]);

            $profileData = array_merge(
                $this->mapProfileFields($data),
                [
                    'vendor_id' => $vendor->id,
                    'business_name' => $data['business_name'],
                    'owner_name' => $data['owner_name'],
                    'email' => $data['email'],
                    'logo_path' => null,
                    'onboarding_completed_at' => now(),
                ]
            );

            if ($this->termsAccepted($data)) {
                $profileData['terms_accepted_at'] = now();
            }

            $vendor->setRelation('profile', VendorProfile::create($profileData));
            $vendor->setRelation('user', $user);

            if (! empty($data['category_ids']) && is_array($data['category_ids'])) {
                $this->application->syncCategories($vendor, $data['category_ids']);
            }

            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => null,
                'action' => 'submitted_for_review',
                'old_status' => null,
                'new_status' => VendorStatus::UnderReview->value,
                'notes' => 'Vendor registration submitted for admin review.',
            ]);

            return $vendor;
        });

        // File storage after commit (avoids holding DB locks during multi-MB uploads).
        $documents = $this->storeRegistrationUploads($vendor, $logo, $documentFiles);
        $vendor->setRelation('documents', collect($documents));

        $vendorId = $vendor->id;
        dispatch(function () use ($vendorId) {
            $fresh = Vendor::query()->with('profile')->find($vendorId);
            if ($fresh) {
                app(VendorAdminNotifier::class)->newRegistration($fresh);
            }
        })->afterResponse();

        return $vendor;
    }

    /**
     * Lean payload for the mobile thank-you screen (avoids extra relation queries).
     *
     * @return array<string, mixed>
     */
    public function registrationResponsePayload(Vendor $vendor): array
    {
        $profile = $vendor->profile;
        $documents = $vendor->relationLoaded('documents')
            ? $vendor->documents
            : $vendor->documents()->get(['id', 'vendor_id', 'type', 'file_path', 'original_name', 'verification_status', 'created_at', 'updated_at']);

        $docCount = $documents->count();
        // Full wizard submit ≈ profile + terms + required docs; categories optional.
        $completion = $docCount >= 2 ? 95 : ($docCount === 1 ? 85 : 75);

        return [
            'vendor_id' => $vendor->id,
            'status' => $vendor->status,
            'logo_url' => $vendor->logo_url,
            'profile' => $profile,
            'documents' => $documents->values(),
            'completion_percent' => $completion,
        ];
    }

    /**
     * @param  array<string, UploadedFile|null>  $documentFiles
     * @return list<VendorDocument>
     */
    private function storeRegistrationUploads(Vendor $vendor, ?UploadedFile $logo, array $documentFiles): array
    {
        $documents = [];

        if ($logo) {
            $logoPath = $this->storeAndOptimizeImage($logo, 'vendors/logos');
            $vendor->profile?->update(['logo_path' => $logoPath]);
            if ($vendor->profile) {
                $vendor->profile->logo_path = $logoPath;
            }
        }

        foreach ($documentFiles as $type => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            if (! in_array($type, VendorDocumentType::values(), true)) {
                continue;
            }

            // New vendor — skip replace lookup used by general upload().
            $path = $file->store("vendors/{$vendor->id}/documents", 'public');
            $documents[] = VendorDocument::create([
                'vendor_id' => $vendor->id,
                'type' => $type,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'verification_status' => 'pending',
            ]);
        }

        return $documents;
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
                $updates['logo_path'] = $this->storeAndOptimizeImage($logo, 'vendors/logos');
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
        ?UploadedFile $profilePicture = null,
        ?UploadedFile $logo = null
    ): Vendor {
        $data = $this->mapEditProfileInput($data);

        return DB::transaction(function () use ($vendor, $data, $profilePicture, $logo) {
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

            if ($profilePicture) {
                if ($profile->profile_picture_path) {
                    Storage::disk('public')->delete($profile->profile_picture_path);
                }
                $updates['profile_picture_path'] = $this->storeAndOptimizeImage(
                    $profilePicture,
                    'vendors/profile-pictures'
                );
            }

            if ($logo) {
                if ($profile->logo_path) {
                    Storage::disk('public')->delete($profile->logo_path);
                }
                $updates['logo_path'] = $this->storeAndOptimizeImage($logo, 'vendors/logos');
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

    private function storeAndOptimizeImage(UploadedFile $file, string $directory): string
    {
        $path = $file->store($directory, 'public');

        // Defer GD compression until after the HTTP response so large camera
        // uploads cannot hold the registration DB transaction / hang the mobile spinner.
        OptimizePublicDiskImageJob::dispatch($path, 'vendor')->afterResponse();

        return $path;
    }

    private function ensureVendorRole(User $user): void
    {
        try {
            if (! class_exists(Role::class)) {
                return;
            }

            $role = Role::findOrCreate('vendor', 'web');
            // Fast path: skip Spatie assignRole (permission cache flush) on hot signup.
            DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore([
                'role_id' => $role->id,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->getKey(),
            ]);
        } catch (\Throwable) {
            // Spatie optional / already attached via users.role
        }
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
