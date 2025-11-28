<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadVisitPhotoRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => 'nullable|string|in:before,after',
            'photo' => 'nullable|file|image|max:5120',
        ];
    }
}
