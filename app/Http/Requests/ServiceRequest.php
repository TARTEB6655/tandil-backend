<?php

namespace App\Http\Requests;

class ServiceRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $param = $this->route('service') ?? $this->route('id');
        $id = $param instanceof \App\Models\Service ? $param->id : $param;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH') || ($this->isMethod('POST') && $this->route('id'));

        return [
            'name' => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:services,slug,' . $id,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'image_remove' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'category_id' => 'nullable|integer|exists:categories,id',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }
}
