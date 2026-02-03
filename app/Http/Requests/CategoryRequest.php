<?php

namespace App\Http\Requests;

class CategoryRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // allow all for now; later you can add roles
    }

    public function rules(): array
    {
        $param = $this->route('category') ?? $this->route('id');
        $id = $param instanceof \App\Models\Category ? $param->id : $param;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'        => $isUpdate ? 'nullable|string|max:255' : 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:categories,slug,' . $id,
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,jpg,png,webp',
        ];
    }
}
