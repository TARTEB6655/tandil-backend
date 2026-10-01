<?php

namespace App\Services\Vendor;

use App\Models\Area;
use App\Models\Category;
use App\Models\ContractorBank;
use App\Models\ContractorCity;
use App\Models\ContractorRegistrationField;
use App\Models\Emirate;
use App\Models\Service;
use Database\Seeders\ContractorRegistrationConfigSeeder;
use Illuminate\Support\Facades\Schema;

class ContractorRegistrationSchemaService
{
    /**
     * Public registration form schema for the mobile app (enabled fields only).
     *
     * @return array{sections: list<array<string, mixed>>, options: array<string, mixed>, version: string, admin_fields: list<array<string, mixed>>}
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
            'version' => 'contractor-registration-v2',
            'status_after_submit' => 'pending',
            'status_label' => 'Pending Review',
            'sections' => $sections,
            'options' => $options,
            // Not registration inputs — returned/managed after submit & by admin.
            'admin_fields' => [
                ['key' => 'account_approval_status', 'label' => 'Account Approval Status', 'field_type' => 'status'],
                ['key' => 'rejection_reason', 'label' => 'Rejection Reason', 'field_type' => 'text'],
                ['key' => 'missing_documents_reason', 'label' => 'Missing Documents Reason', 'field_type' => 'text'],
                ['key' => 'employee_id', 'label' => 'Employee ID', 'field_type' => 'text'],
                ['key' => 'assigned_zone_ids', 'label' => 'Assigned Zone IDs', 'field_type' => 'multiselect', 'option_source' => 'service_coverage_areas'],
            ],
            'file_uploads' => [
                'trade_license_upload',
                'vat_certificate',
                'bank_confirmation_letter',
            ],
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function optionCatalog(): array
    {
        $emirates = [];
        $cities = [];
        $banks = [];
        $mainCategories = [];
        $subcategories = [];
        $services = [];
        $areas = [];

        if (Schema::hasTable('contractor_banks')) {
            $banks = ContractorBank::query()->active()->ordered()->get()
                ->map(fn (ContractorBank $b) => [
                    'id' => $b->id,
                    'value' => $b->name,
                    'name' => $b->name,
                    'name_ar' => $b->name_ar,
                    'slug' => $b->slug,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('emirates')) {
            $emirates = Emirate::query()
                ->active()
                ->when(Schema::hasColumn('emirates', 'contractor_signup_enabled'), fn ($q) => $q->where('contractor_signup_enabled', true))
                ->orderBy('name')->get()
                ->map(fn (Emirate $e) => [
                    'id' => $e->id,
                    'value' => $e->name,
                    'name' => $e->name,
                    'slug' => $e->slug,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('contractor_cities')) {
            $cities = ContractorCity::query()
                ->active()
                ->when(Schema::hasColumn('contractor_cities', 'contractor_signup_enabled'), fn ($q) => $q->where('contractor_signup_enabled', true))
                ->ordered()->get()
                ->map(fn (ContractorCity $c) => [
                    'id' => $c->id,
                    'value' => $c->name,
                    'name' => $c->name,
                    'name_ar' => $c->name_ar,
                    'emirate_id' => $c->emirate_id,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('categories')) {
            $hasParent = Schema::hasColumn('categories', 'parent_id');
            $base = Category::query()
                ->when(Schema::hasColumn('categories', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->when(Schema::hasColumn('categories', 'contractor_signup_enabled'), fn ($q) => $q->where('contractor_signup_enabled', true))
                ->when(Schema::hasColumn('categories', 'sort_order'), fn ($q) => $q->orderBy('sort_order'))
                ->orderBy('name');

            $mainQuery = (clone $base);
            if ($hasParent) {
                $mainQuery->whereNull('parent_id');
            }
            $mainCategories = $mainQuery->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'value' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
                ->values()
                ->all();

            if ($hasParent) {
                $subcategories = (clone $base)->whereNotNull('parent_id')->get(['id', 'name', 'slug', 'parent_id'])
                    ->map(fn (Category $c) => [
                        'id' => $c->id,
                        'value' => $c->id,
                        'name' => $c->name,
                        'slug' => $c->slug,
                        'parent_id' => $c->parent_id,
                    ])
                    ->values()
                    ->all();
            }
        }

        if (Schema::hasTable('services')) {
            $services = Service::query()
                ->where('is_active', true)
                ->when(Schema::hasColumn('services', 'contractor_signup_enabled'), fn ($q) => $q->where('contractor_signup_enabled', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'category_id'])
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'value' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'category_id' => $s->category_id,
                ])
                ->values()
                ->all();
        }

        if (Schema::hasTable('areas')) {
            $areas = Area::query()
                ->when(Schema::hasColumn('areas', 'is_active'), fn ($q) => $q->where('is_active', true))
                ->when(Schema::hasColumn('areas', 'contractor_signup_enabled'), fn ($q) => $q->where('contractor_signup_enabled', true))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Area $a) => ['id' => $a->id, 'value' => $a->id, 'name' => $a->name])
                ->values()
                ->all();
        }

        return [
            'banks' => $banks,
            'emirates' => $emirates,
            'cities' => $cities,
            'main_service_categories' => $mainCategories,
            'service_subcategories' => $subcategories,
            'selected_services' => $services,
            'service_coverage_areas' => $areas,
            // Aliases used by option_source on some fields
            'categories' => $mainCategories,
            'services' => $services,
            'areas' => $areas,
        ];
    }

    /**
     * Slim catalog for contractor app signup dropdowns only.
     *
     * @return array{
     *   main_service_categories: list<array<string, mixed>>,
     *   service_subcategories: list<array<string, mixed>>,
     *   selected_services: list<array<string, mixed>>,
     *   emirates: list<array<string, mixed>>,
     *   service_coverage_areas: list<array<string, mixed>>
     * }
     */
    public function contractorSignupOptions(): array
    {
        $catalog = $this->optionCatalog();

        return [
            'main_service_categories' => $catalog['main_service_categories'] ?? [],
            'service_subcategories' => $catalog['service_subcategories'] ?? [],
            'selected_services' => $catalog['selected_services'] ?? [],
            'emirates' => $catalog['emirates'] ?? [],
            'service_coverage_areas' => $catalog['service_coverage_areas'] ?? [],
        ];
    }

    public function ensureDefaultsSeeded(): void
    {
        if (! Schema::hasTable('contractor_registration_fields')) {
            return;
        }

        $existing = ContractorRegistrationField::query()->orderBy('key')->pluck('key')->all();
        $allowed = ContractorRegistrationConfigSeeder::ALLOWED_KEYS;
        sort($allowed);

        if ($existing !== $allowed) {
            (new ContractorRegistrationConfigSeeder)->run();
        }
    }
}
