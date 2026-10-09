<?php

namespace App\Services\Admin;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Facades\File;

final class PlatformServicesRestoreService
{
    /**
     * @return array{restored: int, skipped: int, actions: list<array<string, mixed>>}
     */
    public function run(bool $dryRun = false, ?string $onlySlug = null): array
    {
        $path = database_path('data/tandil_platform_services_snapshot.json');
        if (! File::isFile($path)) {
            throw new \RuntimeException('Missing snapshot: '.$path);
        }

        $payload = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = $payload['services'] ?? [];
        if ($onlySlug !== null && $onlySlug !== '') {
            $rows = array_values(array_filter($rows, fn (array $row): bool => ($row['slug'] ?? '') === $onlySlug));
        }

        $restored = 0;
        $skipped = 0;
        $actions = [];

        foreach ($rows as $row) {
            $slug = (string) ($row['slug'] ?? '');
            if ($slug === '') {
                $actions[] = ['action' => 'skip', 'reason' => 'empty slug'];
                $skipped++;

                continue;
            }

            if ($this->serviceAlreadyPresent($row)) {
                $actions[] = ['action' => 'skip', 'slug' => $slug, 'reason' => 'service already exists'];
                $skipped++;

                continue;
            }

            $category = $this->findMatchingCategory($row);
            if ($category !== null) {
                $actions[] = [
                    'action' => $dryRun ? 'would_revert_category' : 'revert_category',
                    'slug' => $slug,
                    'category_id' => $category->id,
                    'category_slug' => $category->slug,
                ];
                if (! $dryRun) {
                    $service = CatalogConversionService::convertCategoryToService($category);
                    $this->applySnapshotFields($service, $row);
                    $actions[count($actions) - 1]['service_id'] = $service->id;
                }
                $restored++;

                continue;
            }

            $actions[] = ['action' => $dryRun ? 'would_create' : 'create', 'slug' => $slug];
            if (! $dryRun) {
                $service = Service::create($this->snapshotToServiceAttributes($row));
                $this->applySnapshotFields($service, $row);
                $actions[count($actions) - 1]['service_id'] = $service->id;
            }
            $restored++;
        }

        return [
            'restored' => $restored,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
            'actions' => $actions,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function serviceAlreadyPresent(array $row): bool
    {
        $slug = (string) ($row['slug'] ?? '');
        $name = (string) ($row['name'] ?? '');

        return Service::query()
            ->listedInCatalog()
            ->where(function ($q) use ($slug, $name): void {
                if ($slug !== '') {
                    $q->where('slug', $slug)
                        ->orWhere('slug', 'like', $slug.'-%');
                }
                if ($name !== '') {
                    $q->orWhere('name', $name);
                }
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function findMatchingCategory(array $row): ?Category
    {
        $slug = (string) ($row['slug'] ?? '');
        $name = (string) ($row['name'] ?? '');

        if ($slug === '' && $name === '') {
            return null;
        }

        return Category::query()
            ->listedInCatalog()
            ->where('is_active', true)
            ->where(function ($q) use ($slug, $name): void {
                if ($slug !== '') {
                    $q->where('slug', $slug)
                        ->orWhere('slug', 'like', $slug.'-%');
                }
                if ($name !== '') {
                    $q->orWhere('name', $name);
                }
            })
            ->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [$slug])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function snapshotToServiceAttributes(array $row): array
    {
        return [
            'vendor_id' => null,
            'name' => $row['name'],
            'slug' => $row['slug'],
            'description' => $row['description'] ?? null,
            'image' => $row['image'] ?? null,
            'icon' => $row['icon'] ?? null,
            'is_active' => (bool) ($row['is_active'] ?? true),
            'contractor_signup_enabled' => true,
            'category_id' => null,
            'sort_order' => (int) ($row['sort_order'] ?? 0),
            'pricing_type' => $row['pricing_type'] ?? 'fixed',
            'price' => $row['price'] ?? null,
            'price_includes' => $row['price_includes'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function applySnapshotFields(Service $service, array $row): void
    {
        $service->forceFill([
            'pricing_type' => $row['pricing_type'] ?? $service->pricing_type,
            'price' => array_key_exists('price', $row) ? $row['price'] : $service->price,
            'price_includes' => $row['price_includes'] ?? $service->price_includes,
            'sort_order' => (int) ($row['sort_order'] ?? $service->sort_order),
            'is_active' => (bool) ($row['is_active'] ?? $service->is_active),
        ])->saveQuietly();
    }
}
