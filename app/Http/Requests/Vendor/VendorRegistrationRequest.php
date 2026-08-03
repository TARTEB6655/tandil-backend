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
        $this->ensureUploadFileExtensions(['logo', 'trade_license', 'emirates_id']);

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

            // Prefer extensions (camera HEIC) but also allow mimes for extensionless uploads.
            'logo' => ['nullable', 'file', 'max:10240', 'extensions:'.self::IMAGE_EXTENSIONS],

            'trade_license' => ['required', 'file', 'max:10240', 'extensions:'.self::DOCUMENT_EXTENSIONS],
            'emirates_id' => ['required', 'file', 'max:10240', 'extensions:'.self::DOCUMENT_EXTENSIONS],

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
            $renamed = new UploadedFile(
                $file->getPathname(),
                $base.'.'.$inferred,
                $clientMime !== '' ? $clientMime : ($detectedMime !== '' ? $detectedMime : null),
                $file->getError(),
                true
            );
            $this->files->set($key, $renamed);
        }

        // Request::file() caches converted uploads; clear so validators see renames.
        $this->convertedFiles = null;
    }
}
