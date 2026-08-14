<?php

namespace App\Http\Requests\Vendor;

use App\Enums\VendorType as VendorTypeEnum;
use App\Models\Emirate;
use App\Models\VendorProfile;
use App\Models\VendorType as VendorTypeModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

abstract class VendorProfileFormRequest extends FormRequest
{
    /**
     * Shared Business Profile field rules.
     *
     * @param  bool  $required  When true, core fields are required; otherwise they are optional (partial update).
     * @return array<string, mixed>
     */
    protected function businessProfileRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'business_name' => [$presence, 'string', 'max:255'],
            'owner_name' => [$presence, 'string', 'max:255'],
            'phone' => [$presence, 'string', 'max:32'],
            'trade_license_number' => [$presence, 'string', 'max:100'],
            'address' => [$presence, 'string', 'max:2000'],
            // Accepts a single slug (legacy) or an array of slugs (multi-select vendor type chips).
            'vendor_type' => [$presence, function (string $attribute, mixed $value, \Closure $fail) {
                $allowed = $this->allowedVendorTypeSlugs();
                $values = is_array($value) ? $value : [$value];

                if ($values === []) {
                    $fail('Please select a valid vendor type.');

                    return;
                }

                foreach ($values as $single) {
                    if (! in_array($single, $allowed, true)) {
                        $fail('Please select a valid vendor type.');

                        return;
                    }
                }
            }],
            'emirate' => [$presence, 'string', 'max:100', Rule::in($this->allowedEmirateNames())],
            'city' => ['nullable', 'string', 'max:100'],
            'google_maps_location' => [$presence, 'string', 'max:500'],
            'bank_name' => [$presence, 'string', 'max:191'],
            'iban' => [$presence, 'string', 'max:64'],
            'account_holder_name' => [$presence, 'string', 'max:191'],
            'delivery_radius' => [$presence, 'numeric', 'min:0', 'max:10000'],
            'operating_hours' => [$presence, 'string', 'max:500'],
            'opens_at' => ['nullable', 'date_format:H:i'],
            'closes_at' => ['nullable', 'date_format:H:i'],
            'minimum_order_amount' => [$presence, 'numeric', 'min:0', 'max:1000000'],

            // Optional everywhere
            'vat_number' => ['nullable', 'string', 'max:64'],
            'tax_vat_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
            'years_in_business' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Validation messages shared across vendor profile forms.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vendor_type.in' => 'Please select a valid vendor type.',
            'emirate.in' => 'Please select a valid emirate.',
            'terms_accepted.accepted' => 'You must accept the Terms & Conditions to continue.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Accept "vat_number" as an alias for the stored tax_vat_number column.
        if ($this->filled('vat_number') && ! $this->filled('tax_vat_number')) {
            $this->merge(['tax_vat_number' => $this->input('vat_number')]);
        }

        $this->normalizeVendorFieldAliases();
        $this->normalizeVendorTypeAndEmirate();
        $this->normalizeTimeFields(['opens_at', 'closes_at']);
        $this->normalizeOperatingHoursFromTimes();
    }

    protected function normalizeVendorFieldAliases(): void
    {
        $aliases = [
            'company_name' => 'business_name',
            'authorized_person_name' => 'owner_name',
            'delivery_radius_km' => 'delivery_radius',
            'business_type' => 'vendor_type',
            'store_type' => 'vendor_type',
            'shop_type' => 'vendor_type',
            'type' => 'vendor_type',
        ];

        foreach ($aliases as $from => $to) {
            if ($this->filled($from) && ! $this->filled($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }
    }

    /**
     * Resolve against admin-managed DB rows first, then legacy enum helpers.
     */
    protected function normalizeVendorTypeAndEmirate(): void
    {
        $merge = [];

        if ($this->filled('vendor_type')) {
            $raw = $this->input('vendor_type');

            if (is_array($raw)) {
                // Multi-select: resolve each chip to its slug, drop unknown/unresolvable entries.
                $resolvedSlugs = [];
                foreach ($raw as $item) {
                    $slug = VendorTypeModel::resolveToSlug($item) ?? VendorTypeEnum::resolve($item);
                    if ($slug !== null && ! in_array($slug, $resolvedSlugs, true)) {
                        $resolvedSlugs[] = $slug;
                    }
                }
                if ($resolvedSlugs !== []) {
                    $merge['vendor_type'] = $resolvedSlugs;
                }
            } else {
                $resolved = VendorTypeModel::resolveToSlug($raw) ?? VendorTypeEnum::resolve($raw);
                if ($resolved !== null) {
                    $merge['vendor_type'] = $resolved;
                } elseif (is_string($raw) && str_contains($raw, ',')) {
                    // Some mobile multipart clients can't send real arrays; accept "a,b,c" too.
                    $resolvedSlugs = [];
                    foreach (explode(',', $raw) as $item) {
                        $slug = VendorTypeModel::resolveToSlug(trim($item)) ?? VendorTypeEnum::resolve(trim($item));
                        if ($slug !== null && ! in_array($slug, $resolvedSlugs, true)) {
                            $resolvedSlugs[] = $slug;
                        }
                    }
                    if ($resolvedSlugs !== []) {
                        $merge['vendor_type'] = $resolvedSlugs;
                    }
                }
            }
        }

        if ($this->filled('emirate')) {
            $resolved = Emirate::resolveToName($this->input('emirate'));
            if ($resolved !== null) {
                $merge['emirate'] = $resolved;
            } else {
                $raw = strtolower(trim((string) $this->input('emirate')));
                foreach (VendorProfile::emirates() as $emirate) {
                    if ($raw === strtolower($emirate) || $raw === strtolower(str_replace(' ', '', $emirate))) {
                        $merge['emirate'] = $emirate;
                        break;
                    }
                }
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /** @return list<string> */
    protected function allowedVendorTypeSlugs(): array
    {
        if ($this->vendorTypesTableReady()) {
            $slugs = VendorTypeModel::activeSlugs();
            if ($slugs !== []) {
                return $slugs;
            }
        }

        return VendorTypeEnum::values();
    }

    /** @return list<string> */
    protected function allowedEmirateNames(): array
    {
        return VendorProfile::emirates();
    }

    protected function vendorTypesTableReady(): bool
    {
        try {
            return Schema::hasTable('vendor_types');
        } catch (\Throwable) {
            return false;
        }
    }

    protected function normalizeOperatingHoursFromTimes(): void
    {
        if ($this->filled('operating_hours')) {
            return;
        }

        $opensAt = trim((string) $this->input('opens_at'));
        $closesAt = trim((string) $this->input('closes_at'));

        if ($opensAt !== '' && $closesAt !== '') {
            $this->merge([
                'operating_hours' => $opensAt.' - '.$closesAt,
            ]);
        }
    }

    /**
     * Normalize mobile time pickers (e.g. 6:00, 06:00:00) to HH:MM for validation/storage.
     *
     * @param  list<string>  $keys
     */
    protected function normalizeTimeFields(array $keys): void
    {
        $merge = [];

        foreach ($keys as $key) {
            if (! $this->exists($key)) {
                continue;
            }

            $normalized = self::normalizeTimeValue($this->input($key));
            if ($normalized !== null) {
                $merge[$key] = $normalized;
            }
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public static function normalizeTimeValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) !== 1) {
            return $value;
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            return $value;
        }

        return sprintf('%02d:%02d', $hour, $minute);
    }
}
