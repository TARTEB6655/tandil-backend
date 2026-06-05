<?php

namespace App\Http\Requests\Vendor;

use App\Models\Vendor;
use App\Support\VendorContext;
use Illuminate\Validation\Rule;

class UpdateVendorProfileRequest extends VendorProfileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resolve the vendor being edited from the route binding, request attribute, or current user.
     */
    protected function resolveVendor(): ?Vendor
    {
        $routeVendor = $this->route('vendor');
        if ($routeVendor instanceof Vendor) {
            return $routeVendor->loadMissing('profile');
        }

        $attrVendor = $this->attributes->get('vendor');
        if ($attrVendor instanceof Vendor) {
            return $attrVendor->loadMissing('profile');
        }

        $user = $this->user();

        return $user ? VendorContext::vendorForUser($user) : null;
    }

    /**
     * Whether all core profile fields are required (full onboarding save) or optional (partial edit).
     */
    protected function requireAllFields(): bool
    {
        return $this->boolean('require_all', true);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendor = $this->resolveVendor();
        $profileId = $vendor?->profile?->id;
        $userId = $vendor?->user_id;

        return array_merge($this->businessProfileRules($this->requireAllFields()), [
            'email' => [
                $this->requireAllFields() ? 'required' : 'sometimes',
                'email',
                'max:255',
                Rule::unique('vendor_profiles', 'email')->ignore($profileId),
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'logo' => ['nullable', 'image', 'max:5120'],
            'logo_remove' => ['nullable', 'boolean'],

            'trade_license' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'emirates_id' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],

            'terms_accepted' => ['sometimes', 'accepted'],

            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }
}
