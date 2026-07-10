<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class VendorBulkProductActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => 'required|in:enable,disable,approve,reject,delete',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:vendor_products,id',
            'reason' => 'required_if:action,reject|nullable|string|max:1000',
        ];
    }
}
