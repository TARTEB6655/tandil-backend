<?php

namespace App\Http\Requests\Vendor;

class VendorRegistrationRequest extends VendorProfileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mobile app sends company_name / authorized_person_name; map to stored columns.
     * Accept opens_at + closes_at (HH:MM) and build operating_hours for storage.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeVendorFieldAliases();
        $this->normalizeOperatingHoursFromTimes();

        $name = trim((string) $this->input('name'));
        if ($name !== '') {
            $this->merge([
                'business_name' => $this->input('business_name') ?: $name,
                'owner_name' => $this->input('owner_name') ?: $name,
            ]);
        }
    }

    /**
     * Full vendor sign-up matches the mobile registration wizard (one multipart submit).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shared = $this->businessProfileRules(true);

        // City is optional on the mobile form.
        $shared['city'] = ['nullable', 'string', 'max:100'];

        return array_merge($shared, [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'authorized_person_name' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:vendor_profiles,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms_accepted' => ['required', 'accepted'],

            'logo' => ['nullable', 'image', 'max:5120'],

            'trade_license' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'emirates_id' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],

            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }
}
