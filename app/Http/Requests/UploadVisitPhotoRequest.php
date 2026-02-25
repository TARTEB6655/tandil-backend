<?php

namespace App\Http\Requests;

class UploadVisitPhotoRequest extends BaseFormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'nullable|string|in:before,after',
            'photo' => 'nullable|file|image',
        ];
    }
}
