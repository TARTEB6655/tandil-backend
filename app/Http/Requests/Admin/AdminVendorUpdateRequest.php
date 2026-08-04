<?php

namespace App\Http\Requests\Admin;

use App\Enums\VendorStatus;
use App\Enums\VendorType as VendorTypeEnum;
use App\Http\Requests\Vendor\VendorProfileFormRequest;
use App\Models\Vendor;
use App\Models\VendorType as VendorTypeModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Admin full edit of vendor profile — same fields as registration; all optional (partial update).
 */
class AdminVendorUpdateRequest extends VendorProfileFormRequest
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = 'jpeg,jpg,png,gif,webp';

    /** @var list<string> */
    private const DOCUMENT_EXTENSIONS = 'pdf,jpeg,jpg,png,webp';

    public function authorize(): bool
    {
        return true;
    }

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

        $this->normalizeAdminVendorTypeFallback();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vendor = Vendor::query()->with(['user', 'profile'])->find((int) $this->route('id'));
        $userId = $vendor?->user_id;
        $profileId = $vendor?->profile?->id;

        $allowedVendorTypeSlugs = $this->allowedVendorTypeSlugs();

        return array_merge($this->businessProfileRules(false), [
            'business_name' => ['sometimes', 'string', 'max:255'],
            'authorized_person_name' => ['sometimes', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
                Rule::unique('vendor_profiles', 'email')->ignore($profileId),
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            'terms_accepted' => ['sometimes', 'nullable', 'accepted'],
            'logo' => ['nullable', 'file', 'max:102400', 'extensions:'.self::IMAGE_EXTENSIONS],
            'logo_remove' => ['sometimes', 'nullable', 'boolean'],
            'trade_license' => ['nullable', 'file', 'max:102400', 'extensions:'.self::DOCUMENT_EXTENSIONS],
            'emirates_id' => ['nullable', 'file', 'max:102400', 'extensions:'.self::DOCUMENT_EXTENSIONS],
            'category_ids' => ['sometimes', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'vendor_type' => ['sometimes', Rule::in($allowedVendorTypeSlugs)],
            'status' => ['sometimes', 'nullable', Rule::in([VendorStatus::Approved->value, VendorStatus::UnderReview->value, VendorStatus::Pending->value])],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'email.unique' => 'This email is already registered by another account.',
            'phone.unique' => 'This phone number is already registered by another account.',
            'logo.extensions' => 'Logo must be a JPEG, PNG, GIF, or WebP image.',
            'trade_license.extensions' => 'Trade license must be a PDF or image (JPEG, PNG, WebP).',
            'emirates_id.extensions' => 'Emirates ID must be a PDF or image (JPEG, PNG, WebP).',
            'status.in' => 'Status must be approved, under_review, or pending.',
        ]);
    }

    protected function allowedVendorTypeSlugs(): array
    {
        $enumSlugs = VendorTypeEnum::values();

        if (! $this->vendorTypesTableReady()) {
            return $enumSlugs;
        }

        $dbSlugs = VendorTypeModel::query()->orderBy('name')->pluck('slug')->all();

        return array_values(array_unique(array_merge($dbSlugs, $enumSlugs)));
    }

    protected function normalizeAdminVendorTypeFallback(): void
    {
        if (! $this->filled('vendor_type')) {
            return;
        }

        $resolvedType = VendorTypeModel::resolveToSlug($this->input('vendor_type'))
            ?? VendorTypeEnum::resolve($this->input('vendor_type'));

        if ($resolvedType === null) {
            return;
        }

        $this->merge(['vendor_type' => $resolvedType]);
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

        $this->convertedFiles = null;
    }
}
