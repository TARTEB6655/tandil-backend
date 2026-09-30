<?php

namespace App\Services\Supervisor;

use App\Models\Area;
use App\Models\Category;
use App\Models\ContractorCity;
use App\Models\Emirate;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ContractorSignupOptionsService
{
    public const SETTING_KEY = 'contractor_registration_open';

    public const TABS = [
        'categories' => ['key' => 'categories', 'label' => 'Categories', 'icon' => 'grid'],
        'subcategories' => ['key' => 'subcategories', 'label' => 'Subcategories', 'icon' => 'layers'],
        'services' => ['key' => 'services', 'label' => 'Services', 'icon' => 'tools'],
        'emirates' => ['key' => 'emirates', 'label' => 'Emirates', 'icon' => 'pin'],
        'cities' => ['key' => 'cities', 'label' => 'Cities', 'icon' => 'building'],
        'coverage' => ['key' => 'coverage', 'label' => 'Coverage', 'icon' => 'map'],
    ];

    public function isRegistrationOpen(): bool
    {
        $raw = Setting::get(self::SETTING_KEY, '1');

        return in_array(strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
    }

    public function setRegistrationOpen(bool $open): array
    {
        Setting::set(self::SETTING_KEY, $open ? '1' : '0', 'boolean', 'contractor');

        return $this->registrationPayload();
    }

    public function registrationPayload(): array
    {
        $open = $this->isRegistrationOpen();

        return [
            'is_open' => $open,
            'title' => 'Contractor registration',
            'subtitle' => $open
                ? 'Signup is open — options below appear on contractor registration.'
                : 'Signup is closed — contractors cannot register until you reopen it.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function screen(?string $tab = 'categories'): array
    {
        $tab = $this->normalizeTab($tab);
        $items = $this->listItems($tab);
        $total = count($items);
        $active = collect($items)->where('is_active', true)->count();

        return [
            'registration' => $this->registrationPayload(),
            'tabs' => array_values(self::TABS),
            'tab' => $tab,
            'summary' => [
                'total' => $total,
                'active' => $active,
                'label' => "{$total} total · {$active} active on signup",
            ],
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listItems(string $tab): array
    {
        return match ($this->normalizeTab($tab)) {
            'categories' => Category::query()
                ->platformCatalog()
                ->when(Schema::hasColumn('categories', 'parent_id'), fn ($q) => $q->whereNull('parent_id'))
                ->ordered()
                ->get()
                ->map(fn (Category $c) => $this->formatCategory($c, 'categories'))
                ->values()
                ->all(),
            'subcategories' => Category::query()
                ->platformCatalog()
                ->when(Schema::hasColumn('categories', 'parent_id'), fn ($q) => $q->whereNotNull('parent_id'))
                ->with('parent')
                ->ordered()
                ->get()
                ->map(fn (Category $c) => $this->formatCategory($c, 'subcategories'))
                ->values()
                ->all(),
            'services' => Service::query()
                ->platformCatalog()
                ->with('category')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Service $s) => $this->formatService($s))
                ->values()
                ->all(),
            'emirates' => Emirate::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Emirate $e) => $this->formatEmirate($e))
                ->values()
                ->all(),
            'cities' => ContractorCity::query()
                ->with('emirate')
                ->ordered()
                ->get()
                ->map(fn (ContractorCity $c) => $this->formatCity($c))
                ->values()
                ->all(),
            'coverage' => Area::query()
                ->orderBy('name')
                ->get()
                ->map(fn (Area $a) => $this->formatCoverage($a))
                ->values()
                ->all(),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(string $tab, array $data): array
    {
        $tab = $this->normalizeTab($tab);

        return match ($tab) {
            'categories' => $this->formatCategory($this->createCategory($data, null), $tab),
            'subcategories' => $this->formatCategory($this->createCategory($data, (int) ($data['parent_id'] ?? 0) ?: null), $tab),
            'services' => $this->formatService($this->createService($data)),
            'emirates' => $this->formatEmirate($this->createEmirate($data)),
            'cities' => $this->formatCity($this->createCity($data)),
            'coverage' => $this->formatCoverage($this->createCoverage($data)),
            default => throw ValidationException::withMessages(['tab' => ['Unknown tab.']]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(string $tab, int $id, array $data): array
    {
        $tab = $this->normalizeTab($tab);
        $model = $this->findModel($tab, $id);

        return match ($tab) {
            'categories', 'subcategories' => $this->formatCategory($this->updateCategory($model, $data, $tab), $tab),
            'services' => $this->formatService($this->updateService($model, $data)),
            'emirates' => $this->formatEmirate($this->updateEmirate($model, $data)),
            'cities' => $this->formatCity($this->updateCity($model, $data)),
            'coverage' => $this->formatCoverage($this->updateCoverage($model, $data)),
            default => throw ValidationException::withMessages(['tab' => ['Unknown tab.']]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toggle(string $tab, int $id, ?bool $force = null): array
    {
        $tab = $this->normalizeTab($tab);
        $model = $this->findModel($tab, $id);
        $next = $force ?? ! $this->signupEnabled($model);
        $this->setSignupEnabled($model, $next);

        return match ($tab) {
            'categories', 'subcategories' => $this->formatCategory($model->fresh(['parent']), $tab),
            'services' => $this->formatService($model->fresh(['category'])),
            'emirates' => $this->formatEmirate($model->fresh()),
            'cities' => $this->formatCity($model->fresh(['emirate'])),
            'coverage' => $this->formatCoverage($model->fresh()),
            default => throw ValidationException::withMessages(['tab' => ['Unknown tab.']]),
        };
    }

    public function delete(string $tab, int $id): array
    {
        $tab = $this->normalizeTab($tab);
        $model = $this->findModel($tab, $id);
        $payload = match ($tab) {
            'categories', 'subcategories' => $this->formatCategory($model, $tab),
            'services' => $this->formatService($model),
            'emirates' => $this->formatEmirate($model),
            'cities' => $this->formatCity($model),
            'coverage' => $this->formatCoverage($model),
            default => [],
        };

        if ($tab === 'categories' && method_exists($model, 'children') && $model->children()->exists()) {
            throw ValidationException::withMessages([
                'id' => ['Remove or reassign subcategories before deleting this category.'],
            ]);
        }

        $model->delete();

        return array_merge($payload, ['deleted' => true]);
    }

    public function normalizeTab(?string $tab): string
    {
        $tab = strtolower(trim((string) $tab));
        if ($tab === '' || ! isset(self::TABS[$tab])) {
            return 'categories';
        }

        return $tab;
    }

    private function findModel(string $tab, int $id): Model
    {
        return match ($tab) {
            'categories' => Category::query()->platformCatalog()->whereNull('parent_id')->findOrFail($id),
            'subcategories' => Category::query()->platformCatalog()->whereNotNull('parent_id')->with('parent')->findOrFail($id),
            'services' => Service::query()->platformCatalog()->with('category')->findOrFail($id),
            'emirates' => Emirate::query()->findOrFail($id),
            'cities' => ContractorCity::query()->with('emirate')->findOrFail($id),
            'coverage' => Area::query()->findOrFail($id),
            default => throw ValidationException::withMessages(['tab' => ['Unknown tab.']]),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCategory(array $data, ?int $parentId): Category
    {
        if ($parentId !== null) {
            Category::query()->platformCatalog()->whereNull('parent_id')->findOrFail($parentId);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $slug = $this->uniqueCategorySlug($name, $data['slug'] ?? null);

        return Category::query()->create([
            'vendor_id' => null,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
            'contractor_signup_enabled' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => Category::nextSortOrder(),
        ])->load('parent');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateCategory(Category $category, array $data, string $tab): Category
    {
        if (array_key_exists('name', $data)) {
            $category->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data) && trim((string) $data['slug']) !== '') {
            $category->slug = $this->uniqueCategorySlug((string) $data['slug'], (string) $data['slug'], $category->id);
        }
        if ($tab === 'subcategories' && array_key_exists('parent_id', $data)) {
            $parentId = (int) $data['parent_id'];
            Category::query()->platformCatalog()->whereNull('parent_id')->findOrFail($parentId);
            $category->parent_id = $parentId;
        }
        if (array_key_exists('is_active', $data)) {
            $this->setSignupEnabled($category, (bool) $data['is_active']);
        }
        $category->save();

        return $category->fresh(['parent']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createService(array $data): Service
    {
        $name = trim((string) ($data['name'] ?? ''));
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        if ($categoryId) {
            Category::query()->platformCatalog()->findOrFail($categoryId);
        }

        return Service::query()->create([
            'vendor_id' => null,
            'name' => $name,
            'slug' => $this->uniqueServiceSlug($name, $data['slug'] ?? null),
            'category_id' => $categoryId,
            'is_active' => true,
            'contractor_signup_enabled' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => ((int) Service::query()->max('sort_order')) + 1,
        ])->load('category');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateService(Service $service, array $data): Service
    {
        if (array_key_exists('name', $data)) {
            $service->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data) && trim((string) $data['slug']) !== '') {
            $service->slug = $this->uniqueServiceSlug((string) $data['slug'], (string) $data['slug'], $service->id);
        }
        if (array_key_exists('category_id', $data)) {
            $categoryId = $data['category_id'] !== null ? (int) $data['category_id'] : null;
            if ($categoryId) {
                Category::query()->platformCatalog()->findOrFail($categoryId);
            }
            $service->category_id = $categoryId;
        }
        if (array_key_exists('is_active', $data)) {
            $this->setSignupEnabled($service, (bool) $data['is_active']);
        }
        $service->save();

        return $service->fresh(['category']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createEmirate(array $data): Emirate
    {
        $name = trim((string) ($data['name'] ?? ''));

        return Emirate::query()->create([
            'name' => $name,
            'slug' => $this->uniqueEmirateSlug($name, $data['slug'] ?? null),
            'is_active' => true,
            'contractor_signup_enabled' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateEmirate(Emirate $emirate, array $data): Emirate
    {
        if (array_key_exists('name', $data)) {
            $emirate->name = trim((string) $data['name']);
        }
        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $emirate->slug = $this->uniqueEmirateSlug(
                (string) ($data['name'] ?? $emirate->name),
                $data['slug'] ?? $emirate->slug,
                $emirate->id
            );
        }
        if (array_key_exists('is_active', $data)) {
            $this->setSignupEnabled($emirate, (bool) $data['is_active']);
        }
        $emirate->save();

        return $emirate->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCity(array $data): ContractorCity
    {
        $emirateId = isset($data['emirate_id']) ? (int) $data['emirate_id'] : null;
        if ($emirateId) {
            Emirate::query()->findOrFail($emirateId);
        }

        return ContractorCity::query()->create([
            'emirate_id' => $emirateId,
            'name' => trim((string) ($data['name'] ?? '')),
            'name_ar' => $data['name_ar'] ?? null,
            'is_active' => true,
            'contractor_signup_enabled' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => ((int) ContractorCity::query()->max('sort_order')) + 1,
        ])->load('emirate');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateCity(ContractorCity $city, array $data): ContractorCity
    {
        if (array_key_exists('name', $data)) {
            $city->name = trim((string) $data['name']);
        }
        if (array_key_exists('name_ar', $data)) {
            $city->name_ar = $data['name_ar'];
        }
        if (array_key_exists('emirate_id', $data)) {
            $emirateId = $data['emirate_id'] !== null ? (int) $data['emirate_id'] : null;
            if ($emirateId) {
                Emirate::query()->findOrFail($emirateId);
            }
            $city->emirate_id = $emirateId;
        }
        if (array_key_exists('is_active', $data)) {
            $this->setSignupEnabled($city, (bool) $data['is_active']);
        }
        $city->save();

        return $city->fresh(['emirate']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createCoverage(array $data): Area
    {
        return Area::query()->create([
            'name' => trim((string) ($data['name'] ?? '')),
            'description' => $data['description'] ?? null,
            'is_active' => true,
            'contractor_signup_enabled' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateCoverage(Area $area, array $data): Area
    {
        if (array_key_exists('name', $data)) {
            $area->name = trim((string) $data['name']);
        }
        if (array_key_exists('description', $data)) {
            $area->description = $data['description'];
        }
        if (array_key_exists('is_active', $data)) {
            $this->setSignupEnabled($area, (bool) $data['is_active']);
        }
        $area->save();

        return $area->fresh();
    }

    private function formatCategory(Category $c, string $tab): array
    {
        $slug = $c->slug ?: $this->underscoreSlug($c->name);
        $parentName = $c->relationLoaded('parent') ? $c->parent?->name : null;

        return [
            'id' => $c->id,
            'type' => $tab,
            'name' => $c->name,
            'slug' => $slug,
            'parent_id' => $c->parent_id,
            'parent_name' => $parentName,
            'subtitle' => $parentName ? "{$parentName}  `{$slug}`" : $slug,
            'is_active' => $this->signupEnabled($c),
            'actions' => ['edit' => true, 'delete' => true, 'toggle' => true],
        ];
    }

    private function formatService(Service $s): array
    {
        $slug = $s->slug ?: $this->underscoreSlug($s->name);
        $parentName = $s->relationLoaded('category') ? $s->category?->name : null;

        return [
            'id' => $s->id,
            'type' => 'services',
            'name' => $s->name,
            'slug' => $slug,
            'category_id' => $s->category_id,
            'parent_name' => $parentName,
            'subtitle' => trim(($parentName ? $parentName.'  ' : '').'`'.$slug.'`'),
            'is_active' => $this->signupEnabled($s),
            'actions' => ['edit' => true, 'delete' => true, 'toggle' => true],
        ];
    }

    private function formatEmirate(Emirate $e): array
    {
        $slug = $e->slug ?: $this->underscoreSlug($e->name);

        return [
            'id' => $e->id,
            'type' => 'emirates',
            'name' => $e->name,
            'slug' => $slug,
            'subtitle' => $slug,
            'is_active' => $this->signupEnabled($e),
            'actions' => ['edit' => true, 'delete' => true, 'toggle' => true],
        ];
    }

    private function formatCity(ContractorCity $c): array
    {
        $slug = $this->underscoreSlug($c->name);
        $parentName = $c->relationLoaded('emirate') ? $c->emirate?->name : null;

        return [
            'id' => $c->id,
            'type' => 'cities',
            'name' => $c->name,
            'slug' => $slug,
            'emirate_id' => $c->emirate_id,
            'parent_name' => $parentName,
            'subtitle' => $parentName ? "{$parentName}  `{$slug}`" : $slug,
            'is_active' => $this->signupEnabled($c),
            'actions' => ['edit' => true, 'delete' => true, 'toggle' => true],
        ];
    }

    private function formatCoverage(Area $a): array
    {
        $slug = $this->underscoreSlug($a->name);

        return [
            'id' => $a->id,
            'type' => 'coverage',
            'name' => $a->name,
            'slug' => $slug,
            'subtitle' => $slug,
            'is_active' => $this->signupEnabled($a),
            'actions' => ['edit' => true, 'delete' => true, 'toggle' => true],
        ];
    }

    private function signupEnabled(Model $model): bool
    {
        if (! array_key_exists('contractor_signup_enabled', $model->getAttributes())) {
            return true;
        }

        return (bool) $model->getAttribute('contractor_signup_enabled');
    }

    private function setSignupEnabled(Model $model, bool $enabled): void
    {
        if (! Schema::hasColumn($model->getTable(), 'contractor_signup_enabled')) {
            return;
        }
        $model->setAttribute('contractor_signup_enabled', $enabled);
        $model->save();
    }

    private function uniqueCategorySlug(string $name, mixed $slug = null, ?int $ignoreId = null): string
    {
        $base = $this->underscoreSlug($name, $slug) ?: 'category';
        $candidate = $base;
        $i = 1;
        while (Category::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    private function uniqueServiceSlug(string $name, mixed $slug = null, ?int $ignoreId = null): string
    {
        $base = $this->underscoreSlug($name, $slug) ?: 'service';
        $candidate = $base;
        $i = 1;
        while (Service::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    private function uniqueEmirateSlug(string $name, mixed $slug = null, ?int $ignoreId = null): string
    {
        $base = $this->underscoreSlug($name, $slug) ?: 'emirate';
        $candidate = $base;
        $i = 1;
        while (Emirate::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * UI uses underscore slugs (home_maintenance, downtown_zone).
     */
    private function underscoreSlug(string $name, mixed $slug = null): string
    {
        $raw = ($slug !== null && trim((string) $slug) !== '') ? (string) $slug : $name;
        $base = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $raw));
        $base = trim($base, '_');

        return $base;
    }
}
