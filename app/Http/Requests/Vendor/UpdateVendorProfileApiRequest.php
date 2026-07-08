<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVendorProfileApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Edit Profile screen fields only (matches mobile UI).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'profile_picture' => ['sometimes', 'nullable', 'image', 'max:5120'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'address' => ['sometimes', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'store_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'opens_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'closes_at' => ['sometimes', 'nullable', 'date_format:H:i'],
            'delivery_radius_km' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10000'],
            'minimum_order_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'bank_name' => ['sometimes', 'string', 'max:191'],
            'iban' => ['sometimes', 'string', 'max:64'],
            'account_holder_name' => ['sometimes', 'string', 'max:191'],

            'owner_name' => ['prohibited'],
            'authorized_person_name' => ['prohibited'],
            'contact_person_name' => ['prohibited'],
            'company_name' => ['prohibited'],
            'description' => ['prohibited'],
            'delivery_radius' => ['prohibited'],
            'email' => ['prohibited'],
            'emirate' => ['prohibited'],
            'google_maps_location' => ['prohibited'],
            'operating_hours' => ['prohibited'],
            'facebook_url' => ['prohibited'],
            'instagram_url' => ['prohibited'],
            'twitter_url' => ['prohibited'],
            'website_url' => ['prohibited'],
            'logo_remove' => ['prohibited'],
            'banner' => ['prohibited'],
            'banner_remove' => ['prohibited'],
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
            'terms_accepted' => ['prohibited'],
            'category_ids' => ['prohibited'],
            'trade_license' => ['prohibited'],
            'emirates_id' => ['prohibited'],
            'require_all' => ['prohibited'],
        ];
    }
}
