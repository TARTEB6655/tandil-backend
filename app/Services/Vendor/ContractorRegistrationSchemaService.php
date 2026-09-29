<?php

namespace App\Services\Vendor;

use App\Models\Area;
use App\Models\Category;
use App\Models\ContractorBank;
use App\Models\ContractorCity;
use App\Models\ContractorRegistrationField;
use App\Models\Emirate;
use App\Models\Service;
use Illuminate\Support\Facades\Schema;

class ContractorRegistrationSchemaService
{
    /**
     * Public registration form schema for the mobile app (enabled fields only).
     *
     * @return array{sections: list<array<string, mixed>>, options: array<string, mixed>, version: string}
     */
    public function publicSchema(): array
    {
        $this->ensureDefaultsSeeded();

        $fields = ContractorRegistrationField::query()
            ->enabled()
            ->ordered()
            ->get();

        $options = $this->optionCatalog();

        $bySection = [];
        foreach ($fields as $field) {
            $source = $field->option_source;
            $fieldOptions = null;
            if (is_string($source) && $source !== '' && isset($options[$source])) {
                $fieldOptions = $options[$source];
            }
            $bySection[$field->section][] = $field->toPublicArray($fieldOptions);
        }

        $sectionMeta = [
            'personal' => ['key' => 'personal', 'title' => 'Personal Information', 'title_ar' => 'المعلومات الشخصية'],
            'company' => ['key' => 'company', 'title' => 'Company Information', 'title_ar' => 'معلومات الشركة'],
            'bank' => ['key' => 'bank', 'title' => 'Bank Information', 'title_ar' => 'المعلومات البنكية'],
            'services' => ['key' => 'services', 'title' => 'Categories and Services', 'title_ar' => 'الفئات والخدمات'],
        ];

        $sections = [];
        foreach ($sectionMeta as $key => $meta) {
            if (empty($bySection[$key])) {
                continue;
            }
            $sections[] = array_merge($meta, ['fields' => $bySection[$key]]);
        }

        return [
            'version' => 'contractor-registration-v1',
            'status_after_submit' => 'pending',
            'status_label' => 'Pending Review',
            'sections' => $sections,
            'options' => $options,
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function optionCatalog(): array
    {
        $catalog = [
            'banks' => [],
            'emirates' => [],
            'cities' => [],
            'categories' => [],
            'services' => [],
            'areas' => [],
        ];

        if (Schema::hasTable('contractor_banks')) {
            $catalog['banks'] = ContractorBank::query()->active()->ordered()->get()
                ->map(fn (ContractorBank $b) => $b->toApiArray())
                ->values()
                ->all();
        }

        if (Schema::hasTable('emirates')) {
            $catalog['emirates'] = Emirate::query()->active()->orderBy('name')->get()
                ->map(fn (Emirate $e) => $e->toApiArray())
                ->values()
                ->all();
        }

        if (Schema::hasTable('contractor_cities')) {
            $catalog['cities'] = ContractorCity::query()->active()->ordered()->get()
                ->map(fn (ContractorCity $c) => $c->toApiArray())
                ->values()
                ->all();
        }

        if (Schema::hasTable('categories')) {
            $catalog['categories'] = Category::query()
                ->where(function ($q) {
                    if (Schema::hasColumn('categories', 'is_active')) {
                        $q->where('is_active', true);
                    }
                })
                ->when(Schema::hasColumn('categories', 'sort_order'), fn ($q) => $q->orderBy('sort_order'))
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('services')) {
            $catalog['services'] = Service::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category_id'])
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'category_id' => $s->category_id,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('areas')) {
            $catalog['areas'] = Area::query()
                ->when(Schema::hasColumn('areas', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Area $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                ])
                ->values()
                ->all();
        }

        return $catalog;
    }

    public function ensureDefaultsSeeded(): void
    {
        if (! Schema::hasTable('contractor_registration_fields')) {
            return;
        }

        if (ContractorRegistrationField::query()->exists()) {
            return;
        }

        (new \Database\Seeders\ContractorRegistrationConfigSeeder)->run();
    }

    /**
     * Build Laravel validation rules from enabled field config + always-on account rules.
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array
    {
        $this->ensureDefaultsSeeded();

        $rules = [
            'owner_name' => ['nullable', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:6'],
        ];

        $fields = ContractorRegistrationField::query()->enabled()->get();
        foreach ($fields as $field) {
            if (in_array($field->key, ['password', 'password_confirmation', 'email', 'phone'], true)) {
                continue;
            }

            if ($field->field_type === ContractorRegistrationField::TYPE_MULTISELECT) {
                $rules[$field->key] = $field->is_required
                    ? ['required', 'array', 'min:1']
                    : ['nullable', 'array'];
                $rules[$field->key.'.*'] = $this->multiselectItemRule($field);

                continue;
            }

            $rules[$field->key] = $this->rulesForField($field);
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private function rulesForField(ContractorRegistrationField $field): array
    {
        $base = $field->is_required ? ['required'] : ['nullable'];

        return match ($field->field_type) {
            ContractorRegistrationField::TYPE_EMAIL => array_merge($base, ['email', 'max:255']),
            ContractorRegistrationField::TYPE_TEL => array_merge($base, ['string', 'max:32']),
            ContractorRegistrationField::TYPE_PASSWORD => array_merge($base, ['string', 'min:6']),
            ContractorRegistrationField::TYPE_DATE => array_merge($base, ['date']),
            ContractorRegistrationField::TYPE_TEXTAREA, ContractorRegistrationField::TYPE_TEXT => array_merge($base, ['string', 'max:2000']),
            ContractorRegistrationField::TYPE_FILE => array_merge($base, ['file', 'max:102400']),
            ContractorRegistrationField::TYPE_SELECT => array_merge($base, $this->selectRules($field)),
            default => array_merge($base, ['string', 'max:255']),
        };
    }

    /**
     * @return list<mixed>
     */
    private function selectRules(ContractorRegistrationField $field): array
    {
        return match ($field->option_source) {
            'banks' => ['integer', 'exists:contractor_banks,id'],
            'cities' => ['integer', 'exists:contractor_cities,id'],
            'emirates' => ['string', 'max:100'],
            default => ['string', 'max:255'],
        };
    }

    /**
     * @return list<mixed>
     */
    private function multiselectItemRule(ContractorRegistrationField $field): array
    {
        return match ($field->option_source) {
            'categories' => ['integer', 'exists:categories,id'],
            'services' => ['integer', 'exists:services,id'],
            'areas' => ['integer', 'exists:areas,id'],
            'emirates' => ['integer', 'exists:emirates,id'],
            'cities' => ['integer', 'exists:contractor_cities,id'],
            'banks' => ['integer', 'exists:contractor_banks,id'],
            default => ['integer'],
        };
    }
}
