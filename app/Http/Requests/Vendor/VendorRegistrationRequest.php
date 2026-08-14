<?php

namespace App\Http\Requests\Vendor;

use App\Support\PasswordInput;
use Illuminate\Http\UploadedFile;

class VendorRegistrationRequest extends VendorProfileFormRequest
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = 'jpeg,jpg,png,gif,webp';

    /** @var list<string> */
    private const DOCUMENT_EXTENSIONS = 'pdf,jpeg,jpg,png,webp';

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
        PasswordInput::normalize($this);
        $this->normalizeRegistrationFileAliases();
        $this->normalizeSingleFileUploads(['logo', 'trade_license', 'emirates_id']);
        $this->ensureUploadFileExtensions(['logo', 'trade_license', 'emirates_id']);

        parent::prepareForValidation();

        $name = trim((string) $this->input('name'));
        if ($name !== '') {
            $this->merge([
                'business_name' => $this->input('business_name') ?: $name,
                'owner_name' => $this->input('owner_name') ?: $name,
            ]);
        }

        // Unknown mobile picker values should not block signup — prefer active "other" row.
        $vendorType = $this->input('vendor_type');
        $allowedVendorTypeSlugs = $this->allowedVendorTypeSlugs();
        $hasValidVendorTypeSelection = is_array($vendorType)
            ? array_intersect($vendorType, $allowedVendorTypeSlugs) !== []
            : in_array($vendorType, $allowedVendorTypeSlugs, true);

        if ($this->filled('vendor_type') && ! $hasValidVendorTypeSelection) {
            $fallback = \App\Models\VendorType::query()->active()->where('slug', 'other')->value('slug')
                ?? (\App\Models\VendorType::query()->active()->orderBy('id')->value('slug'))
                ?? 'other';
            $this->merge(['vendor_type' => is_array($vendorType) ? [$fallback] : $fallback]);
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
            'phone' => ['required', 'string', 'max:32', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'terms_accepted' => ['required', 'accepted'],

            // Accept up to 100 MB; server compresses images to under 2 MB before saving.
            'logo' => ['nullable', 'file', 'max:102400', 'extensions:'.self::IMAGE_EXTENSIONS],

            'trade_license' => ['required', 'file', 'max:102400', 'extensions:'.self::DOCUMENT_EXTENSIONS],
            'emirates_id' => ['required', 'file', 'max:102400', 'extensions:'.self::DOCUMENT_EXTENSIONS],

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
            'email.unique' => 'This email is already registered. Please log in or use a different email.',
            'phone.unique' => 'This phone number is already registered. Please log in or use a different phone number.',
            'logo.extensions' => 'Logo must be a JPEG, PNG, GIF, or WebP image (HEIC is not supported — please convert to JPEG/PNG).',
            'logo.max' => 'Logo must not be larger than 100 MB. It will be compressed under 2 MB automatically.',
            'trade_license.required' => 'Trade license document is required.',
            'trade_license.extensions' => 'Trade license must be a PDF or image (JPEG, PNG, WebP). HEIC is not supported.',
            'trade_license.max' => 'Trade license must not be larger than 100 MB. Images are compressed under 2 MB automatically; PDFs must already be under 2 MB.',
            'emirates_id.required' => 'Emirates ID document is required.',
            'emirates_id.extensions' => 'Emirates ID must be a PDF or image (JPEG, PNG, WebP). HEIC is not supported.',
            'emirates_id.max' => 'Emirates ID must not be larger than 100 MB. Images are compressed under 2 MB automatically; PDFs must already be under 2 MB.',
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

    /**
     * Some Android/iOS uploads arrive without a file extension; Laravel's
     * `extensions:` rule then rejects them. Infer an extension from MIME.
     *
     * @param  list<string>  $keys
     */
    protected function ensureUploadFileExtensions(array $keys): void
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'application/pdf' => 'pdf',
        ];

        foreach ($keys as $key) {
            $file = $this->file($key);
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $original = (string) $file->getClientOriginalName();
            $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
            if ($ext !== '') {
                continue;
            }

            $clientMime = strtolower((string) ($file->getClientMimeType() ?: ''));
            $detectedMime = strtolower((string) ($file->getMimeType() ?: ''));
            $inferred = $mimeMap[$clientMime] ?? $mimeMap[$detectedMime] ?? null;
            if ($inferred === null) {
                continue;
            }

            $base = pathinfo($original !== '' ? $original : $key, PATHINFO_FILENAME) ?: $key;
            $newName = $base.'.'.$inferred;

            // Preserve Laravel's Testing\File size/mime overrides used in feature tests.
            if ($file instanceof \Illuminate\Http\Testing\File) {
                $replacement = new \Illuminate\Http\Testing\File($newName, $file->tempFile);
                $replacement->sizeToReport = $file->sizeToReport;
                $replacement->mimeTypeToReport = $file->mimeTypeToReport
                    ?: ($clientMime !== '' ? $clientMime : $detectedMime);
                $this->files->set($key, $replacement);
            } else {
                $renamed = new UploadedFile(
                    $file->getPathname(),
                    $newName,
                    $clientMime !== '' ? $clientMime : ($detectedMime !== '' ? $detectedMime : null),
                    $file->getError(),
                    true
                );
                $this->files->set($key, $renamed);
            }
        }

        // Request::file() caches converted uploads; clear so validators see renames.
        $this->convertedFiles = null;
    }
}
