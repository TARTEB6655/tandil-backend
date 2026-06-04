<?php

namespace App\Services\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorApprovalLog;
use App\Models\VendorProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class VendorRegistrationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, ?UploadedFile $logo = null): Vendor
    {
        return DB::transaction(function () use ($data, $logo) {
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
                'status' => VendorStatus::Pending->value,
            ]);

            $logoPath = $logo ? $logo->store('vendors/logos', 'public') : null;

            VendorProfile::create([
                'vendor_id' => $vendor->id,
                'business_name' => $data['business_name'],
                'owner_name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'tax_vat_number' => $data['tax_vat_number'] ?? null,
                'logo_path' => $logoPath,
                'description' => $data['description'] ?? null,
            ]);

            VendorApprovalLog::create([
                'vendor_id' => $vendor->id,
                'performed_by' => null,
                'action' => 'registered',
                'old_status' => null,
                'new_status' => VendorStatus::Pending->value,
                'notes' => 'Vendor registration submitted.',
            ]);

            return $vendor->load('profile', 'user');
        });
    }

    public function updateProfile(Vendor $vendor, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Vendor
    {
        return DB::transaction(function () use ($vendor, $data, $logo, $removeLogo) {
            $profile = $vendor->profile;
            $updates = array_filter([
                'business_name' => $data['business_name'] ?? null,
                'owner_name' => $data['owner_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'tax_vat_number' => $data['tax_vat_number'] ?? null,
                'description' => $data['description'] ?? null,
            ], fn ($v) => $v !== null);

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

            $profile->update($updates);

            if (isset($data['owner_name'])) {
                $vendor->user->update(['name' => $data['owner_name']]);
            }

            return $vendor->fresh(['profile', 'user']);
        });
    }
}
