<?php

namespace App\Http\Requests;

class CategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('shipping_amount') && ! $this->has('shipping_cost')) {
            $this->merge(['shipping_cost' => $this->input('shipping_amount')]);
        }
        if ($this->has('delivery_type') && ! $this->has('shipping_type')) {
            $this->merge(['shipping_type' => $this->input('delivery_type')]);
        }
    }

    public function rules(): array
    {
        $param = $this->route('category') ?? $this->route('id');
        $id = $param instanceof \App\Models\Category ? $param->id : $param;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH') || ($this->isMethod('POST') && $this->route('id'));

        $shippingRules = $isUpdate
            ? ['nullable', 'numeric', 'min:0']
            : ['required', 'numeric', 'min:0'];

        $typeRules = $isUpdate
            ? ['nullable', 'string', 'in:bike,car']
            : ['required', 'string', 'in:bike,car'];

        $taxRules = $isUpdate
            ? ['nullable', 'numeric', 'min:0', 'max:100']
            : ['required', 'numeric', 'min:0', 'max:100'];

        return [
            'name' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories,slug,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp',
            'image_remove' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'shipping_cost' => $shippingRules,
            'shipping_type' => $typeRules,
            'tax_percentage' => $taxRules,
            'shipping_amount' => 'nullable|numeric|min:0',
            'delivery_type' => 'nullable|string|in:bike,car',
        ];
    }
}
