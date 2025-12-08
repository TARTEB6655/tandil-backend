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
        $id = $this->route('category')?->id;

        return [
            'name'        => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:categories,slug,' . $id, // Made optional - will auto-generate if not provided
            'description' => 'nullable|string',
        ];
    }
}
