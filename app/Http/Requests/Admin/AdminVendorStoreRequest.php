<?php

namespace App\Http\Requests\Admin;

use App\Enums\VendorType as VendorTypeEnum;
use App\Http\Requests\Vendor\VendorRegistrationRequest;
use App\Models\VendorType as VendorTypeModel;
use Illuminate\Validation\Rule;

/**
 * Admin creates a vendor with the same fields as public registration (multipart form-data).
 */
class AdminVendorStoreRequest extends VendorRegistrationRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (! $this->has('terms_accepted')) {
            $this->merge(['terms_accepted' => '1']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['terms_accepted'] = ['nullable', 'accepted'];
        $rules['status'] = ['nullable', Rule::in(['approved', 'under_review', 'pending'])];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'status.in' => 'Status must be approved, under_review, or pending.',
        ]);
    }

    protected function allowedVendorTypeSlugs(): array
    {
        if ($this->vendorTypesTableReady()) {
            $slugs = VendorTypeModel::query()->orderBy('name')->pluck('slug')->all();
            if ($slugs !== []) {
                return $slugs;
            }
        }

        return VendorTypeEnum::values();
    }
}
