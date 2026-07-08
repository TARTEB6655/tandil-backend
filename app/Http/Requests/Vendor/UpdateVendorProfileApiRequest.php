<?php

namespace App\Http\Requests\Vendor;

use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\VendorContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVendorProfileApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendor = $this->resolveVendor();
        $profileId = $vendor?->profile?->id;
        $userId = $vendor?->user_id;

        return [
            'owner_name' => ['sometimes', 'string', 'max:255'],
            'authorized_person_name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('vendor_profiles', 'email')->ignore($profileId),
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => ['sometimes', 'string', 'max:32'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'address' => ['sometimes', 'string', 'max:2000'],
            'emirate' => ['sometimes', 'string', 'max:100', Rule::in(VendorProfile::emirates())],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'google_maps_location' => ['sometimes', 'nullable', 'string', 'max:500'],
            'operating_hours' => ['sometimes', 'nullable', 'string', 'max:500'],
            'opens_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'closes_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'bank_name' => ['sometimes', 'string', 'max:191'],
            'iban' => ['sometimes', 'string', 'max:64'],
            'account_holder_name' => ['sometimes', 'string', 'max:191'],
            'facebook_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'instagram_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'twitter_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'website_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'logo' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'logo_remove' => ['sometimes', 'boolean'],
            'banner' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'banner_remove' => ['sometimes', 'boolean'],

            'password' => ['prohibited'],
            'password_confirmation' => ['prohibited'],
            'current_password' => ['prohibited'],

            'status' => ['prohibited'],
            'commission_rate' => ['prohibited'],
            'vendor_id' => ['prohibited'],
            'trade_license_number' => ['prohibited'],
            'vendor_type' => ['prohibited'],
            'tax_vat_number' => ['prohibited'],
            'vat_number' => ['prohibited'],
            'years_in_business' => ['prohibited'],
            'minimum_order_amount' => ['prohibited'],
            'delivery_radius' => ['prohibited'],
            'terms_accepted' => ['prohibited'],
            'category_ids' => ['prohibited'],
            'trade_license' => ['prohibited'],
            'emirates_id' => ['prohibited'],
            'require_all' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $aliases = [
            'company_name' => 'business_name',
            'authorized_person_name' => 'owner_name',
        ];

        foreach ($aliases as $from => $to) {
            if ($this->filled($from) && ! $this->filled($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }

        if ($this->filled('operating_hours')) {
            return;
        }

        $opensAt = trim((string) $this->input('opens_at'));
        $closesAt = trim((string) $this->input('closes_at'));

        if ($opensAt !== '' && $closesAt !== '') {
            $this->merge([
                'operating_hours' => $opensAt.' - '.$closesAt,
            ]);
        }
    }

    protected function resolveVendor(): ?Vendor
    {
        $user = $this->user();

        return $user ? VendorContext::vendorForUser($user) : null;
    }
}
