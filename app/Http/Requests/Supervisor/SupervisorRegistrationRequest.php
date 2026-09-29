<?php

namespace App\Http\Requests\Supervisor;

use App\Models\ContractorBank;
use App\Models\ContractorCity;
use App\Support\PasswordInput;
use App\Support\UserCredentialRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class SupervisorRegistrationRequest extends FormRequest
{
    private const DOCUMENT_EXTENSIONS = 'pdf,jpeg,jpg,png,webp';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        PasswordInput::normalize($this);

        foreach ([
            'trade_license_upload' => 'trade_license',
        ] as $from => $to) {
            if ($this->files->has($from) && ! $this->files->has($to)) {
                $file = $this->file($from);
                if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
                    $file = $file[0];
                }
                if ($file instanceof UploadedFile) {
                    $this->files->set($to, $file);
                }
            }
        }

        foreach (['trade_license_upload', 'trade_license', 'vat_certificate', 'bank_confirmation_letter'] as $key) {
            $file = $this->file($key);
            if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
                $this->files->set($key, $file[0]);
            }
        }

        if ($this->filled('trade_license_expiry_date') && ! $this->filled('trade_license_expiry')) {
            $this->merge(['trade_license_expiry' => $this->input('trade_license_expiry_date')]);
        }
        if ($this->filled('trade_license_expiry') && ! $this->filled('trade_license_expiry_date')) {
            $this->merge(['trade_license_expiry_date' => $this->input('trade_license_expiry')]);
        }

        if ($this->filled('city') && is_numeric($this->input('city'))) {
            $city = ContractorCity::query()->find((int) $this->input('city'));
            if ($city) {
                $this->merge(['city_id' => $city->id, 'city' => $city->name]);
            }
        }

        if ($this->filled('bank_name') && is_numeric($this->input('bank_name'))) {
            $bank = ContractorBank::query()->find((int) $this->input('bank_name'));
            if ($bank) {
                $this->merge(['bank_id' => $bank->id, 'bank_name' => $bank->name]);
            }
        }

        $this->convertedFiles = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $doc = ['file', 'max:102400', 'extensions:'.self::DOCUMENT_EXTENSIONS];

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', UserCredentialRules::uniquePhone('supervisor')],
            'email' => [
                'required',
                'email',
                'max:255',
                UserCredentialRules::uniqueEmail('supervisor'),
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:6'],

            'company_name' => ['required', 'string', 'max:255'],
            'trade_license_number' => ['required', 'string', 'max:100'],
            'trade_license_upload' => ['nullable', ...$doc],
            'trade_license' => ['required', ...$doc],
            'trade_license_expiry_date' => ['required', 'date'],
            'trn' => ['nullable', 'string', 'max:64'],
            'vat_certificate' => ['nullable', ...$doc],
            'emirate' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'city_id' => ['nullable', 'integer'],
            'company_address' => ['required', 'string', 'max:2000'],

            'bank_name' => ['required', 'string', 'max:191'],
            'bank_id' => ['nullable', 'integer'],
            'account_holder_name' => ['required', 'string', 'max:191'],
            'bank_account_number' => ['required', 'string', 'max:64'],
            'iban' => ['required', 'string', 'max:64'],
            'bank_confirmation_letter' => ['required', ...$doc],

            'main_service_categories' => ['required', 'array', 'min:1'],
            'main_service_categories.*' => ['integer', 'exists:categories,id'],
            'service_subcategories' => ['nullable', 'array'],
            'service_subcategories.*' => ['integer', 'exists:categories,id'],
            'selected_services' => ['required', 'array', 'min:1'],
            'selected_services.*' => ['integer', 'exists:services,id'],
            'emirates' => ['required', 'array', 'min:1'],
            'emirates.*' => ['integer', 'exists:emirates,id'],
            'cities' => ['nullable', 'array'],
            'cities.*' => ['integer', 'exists:contractor_cities,id'],
            'service_coverage_areas' => ['required', 'array', 'min:1'],
            'service_coverage_areas.*' => ['integer', 'exists:areas,id'],
        ];
    }

    /**
     * @return array<string, UploadedFile>
     */
    public function documentFiles(): array
    {
        $out = [];
        $map = [
            'trade_license' => 'trade_license',
            'vat_certificate' => 'vat_certificate',
            'bank_confirmation_letter' => 'bank_confirmation_letter',
        ];
        foreach ($map as $input => $type) {
            $file = $this->file($input) ?? ($input === 'trade_license' ? $this->file('trade_license_upload') : null);
            if ($file instanceof UploadedFile) {
                $out[$type] = $file;
            }
        }

        return $out;
    }
}
