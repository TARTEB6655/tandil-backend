<?php

namespace App\Http\Requests\Vendor;

use Illuminate\Http\UploadedFile;

class VendorRegistrationRequest extends VendorProfileFormRequest
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = 'jpeg,jpg,png,gif,webp,heic,heif';

    /** @var list<string> */
    private const DOCUMENT_EXTENSIONS = 'pdf,jpeg,jpg,png,webp,heic,heif';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mobile app sends company_name / authorized_person_name; map to stored columns.
     * Accept opens_at + closes_at (HH:MM) and build operating_hours for storage.
     */
    protected function prepareForValidation(): void
    {
        $this->normalizeRegistrationFileAliases();
        $this->normalizeSingleFileUploads(['logo', 'trade_license', 'emirates_id']);

        parent::prepareForValidation();

        $name = trim((string) $this->input('name'));
        if ($name !== '') {
            $this->merge([
                'business_name' => $this->input('business_name') ?: $name,
                'owner_name' => $this->input('owner_name') ?: $name,
            ]);
        }
    }

    /**
     * Full vendor sign-up matches the mobile registration wizard (one multipart submit).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shared = $this->businessProfileRules(true);

        // City is optional on the mobile form.
        $shared['city'] = ['nullable', 'string', 'max:100'];

        return array_merge($shared, [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'authorized_person_name' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:vendor_profiles,email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms_accepted' => ['required', 'accepted'],

            'logo' => ['nullable', 'file', 'extensions:'.self::IMAGE_EXTENSIONS, 'max:10240'],

            'trade_license' => ['required', 'file', 'extensions:'.self::DOCUMENT_EXTENSIONS, 'max:10240'],
            'emirates_id' => ['required', 'file', 'extensions:'.self::DOCUMENT_EXTENSIONS, 'max:10240'],

            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],

            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'logo.extensions' => 'Logo must be a JPEG, PNG, GIF, WebP, or HEIC image.',
            'logo.max' => 'Logo must not be larger than 10 MB.',
            'trade_license.required' => 'Trade license document is required.',
            'trade_license.extensions' => 'Trade license must be a PDF or image (JPEG, PNG, WebP, HEIC).',
            'trade_license.max' => 'Trade license must not be larger than 10 MB.',
            'emirates_id.required' => 'Emirates ID document is required.',
            'emirates_id.extensions' => 'Emirates ID must be a PDF or image (JPEG, PNG, WebP, HEIC).',
            'emirates_id.max' => 'Emirates ID must not be larger than 10 MB.',
            'opens_at.date_format' => 'Opening time must be in HH:MM format (e.g. 06:00).',
            'closes_at.date_format' => 'Closing time must be in HH:MM format (e.g. 22:00).',
        ]);
    }

    protected function normalizeRegistrationFileAliases(): void
    {
        $aliases = [
            'company_logo' => 'logo',
            'logo_image' => 'logo',
            'trade_license_file' => 'trade_license',
            'trade_license_document' => 'trade_license',
            'emirates_id_file' => 'emirates_id',
            'emirates_id_document' => 'emirates_id',
        ];

        foreach ($aliases as $from => $to) {
            if (! $this->files->has($from)) {
                continue;
            }

            $aliasFile = $this->files->get($from);

            if (is_array($aliasFile) && isset($aliasFile[0]) && $aliasFile[0] instanceof UploadedFile) {
                $aliasFile = $aliasFile[0];
            }

            if ($aliasFile instanceof UploadedFile) {
                $this->files->set($to, $aliasFile);
            }
        }
    }

    /**
     * @param  list<string>  $keys
     */
    protected function normalizeSingleFileUploads(array $keys): void
    {
        foreach ($keys as $key) {
            $file = $this->file($key);

            if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
                $this->files->set($key, $file[0]);
            }
        }
    }
}
