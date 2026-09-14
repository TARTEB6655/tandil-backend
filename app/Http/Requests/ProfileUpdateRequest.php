<?php

namespace App\Http\Requests;

use App\Support\UserCredentialRules;

class ProfileUpdateRequest extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $role = strtolower((string) ($user->role ?? 'client'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                UserCredentialRules::uniqueEmail($role, $user?->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'min:7',
                'max:20',
                UserCredentialRules::uniquePhone($role, $user?->id),
            ],
        ];
    }
}
