<?php

namespace App\Http\Requests\Vendor;

class VendorRegistrationRequest extends VendorProfileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Registration captures account essentials; remaining Business Profile fields are
     * validated when supplied and fully enforced at the submit-for-approval gate.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->businessProfileRules(false), [
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:vendor_profiles,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms_accepted' => ['sometimes', 'accepted'],

            'logo' => ['nullable', 'image', 'max:5120'],

            // Document uploads (optional at signup, enforced before approval submission)
            'trade_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'emirates_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }
}
