<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Category;
use App\Models\Service;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $services = Service::with('category')
            ->forVendorCatalog($vendor->id)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::success('Services retrieved.', [
            'items' => collect($services->items())->map(fn (Service $s) => $this->toArray($s))->all(),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    public function store(ServiceRequest $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $validated = $request->validated();

        if (! empty($validated['category_id'])) {
            $allowed = Category::forVendorCatalog($vendor->id)->where('id', $validated['category_id'])->exists();
            if (! $allowed) {
                return ApiResponse::error('Invalid category for your store.', 422);
            }
        }

        $name = $validated['name'] ?? '';
        $slug = isset($validated['slug']) && (string) $validated['slug'] !== '' ? $validated['slug'] : Str::slug($name);
        $slug = $this->uniqueSlug($slug);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('services', 'public');
        }

        $service = Service::create([
            'vendor_id' => $vendor->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
            'category_id' => $validated['category_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return ApiResponse::success('Service created.', ['service' => $this->toArray($service->load('category'))], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $service = Service::with('category')->forVendorCatalog($vendor->id)->where('id', $id)->first();
        if ($service === null) {
            return ApiResponse::error('Service not found.', 404);
        }

        return ApiResponse::success('Service retrieved.', ['service' => $this->toArray($service)]);
    }

    public function update(ServiceRequest $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $service = Service::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($service === null) {
            return ApiResponse::error('You can only update services created by your store.', 403);
        }

        $validated = $request->validated();
        if (! empty($validated['category_id'])) {
            $allowed = Category::forVendorCatalog($vendor->id)->where('id', $validated['category_id'])->exists();
            if (! $allowed) {
                return ApiResponse::error('Invalid category for your store.', 422);
            }
        }

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'slug' => $validated['slug'] ?? null,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'category_id' => $validated['category_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('image')) {
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }
            $updates['image'] = $request->file('image')->store('services', 'public');
        } elseif ($request->boolean('image_remove')) {
            if ($service->image) {
                Storage::disk('public')->delete($service->image);
            }
            $updates['image'] = null;
        }

        $service->update($updates);

        return ApiResponse::success('Service updated.', ['service' => $this->toArray($service->fresh('category'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $service = Service::where('vendor_id', $vendor->id)->where('id', $id)->first();
        if ($service === null) {
            return ApiResponse::error('You can only delete services created by your store.', 403);
        }

        if ($service->image) {
            Storage::disk('public')->delete($service->image);
        }
        $service->delete();

        return ApiResponse::success('Service deleted.');
    }

    private function uniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Service $service): array
    {
        return [
            'id' => $service->id,
            'vendor_id' => $service->vendor_id,
            'is_platform' => $service->vendor_id === null,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'image' => $service->image,
            'image_url' => $service->image_url,
            'icon' => $service->icon,
            'is_active' => (bool) $service->is_active,
            'category_id' => $service->category_id,
            'category' => $service->relationLoaded('category') && $service->category ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
            ] : null,
            'sort_order' => (int) ($service->sort_order ?? 0),
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }
}
