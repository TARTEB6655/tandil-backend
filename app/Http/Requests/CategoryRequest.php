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
    }

    public function rules(): array
    {
        $param = $this->route('category') ?? $this->route('id');
        $id = $param instanceof \App\Models\Category ? $param->id : $param;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH') || ($this->isMethod('POST') && $this->route('id'));

        $shippingRules = $isUpdate
            ? ['nullable', 'numeric', 'min:0']
            : ['required', 'numeric', 'min:0'];

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
            'tax_percentage' => $taxRules,
            'shipping_amount' => 'nullable|numeric|min:0',
        ];
    }
}
