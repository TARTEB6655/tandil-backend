<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceRequest;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dedicated Services API (CRUD). Services are a separate table; optional category_id.
 * All routes under /api/admin/services. Auth: Bearer token, admin role.
 */
class ServiceController extends Controller
{
    private function serviceToArray(Service $service, array $extra = []): array
    {
        return array_merge([
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'image' => $service->image,
            'image_url' => $service->image_url,
            'icon' => $service->icon,
            'is_active' => (bool) $service->is_active,
            'coming_soon' => (bool) $service->coming_soon,
            'category_id' => $service->category_id,
            'category' => ($service->relationLoaded('category') && $service->category) ? [
                'id' => $service->category->id,
                'name' => $service->category->name,
                'slug' => $service->category->slug,
            ] : null,
            'sort_order' => (int) ($service->sort_order ?? 0),
            'pricing_type' => $service->pricing_type ?? 'fixed',
            'price' => $service->price !== null ? round((float) $service->price, 2) : null,
            'price_includes' => is_array($service->price_includes) ? $service->price_includes : null,
            'created_at' => $service->created_at?->format('c'),
            'updated_at' => $service->updated_at?->format('c'),
        ], $extra);
    }

    /**
     * GET /api/admin/services – List all services (paginated).
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $query = Service::with('category')->withCount('products')->orderBy('sort_order')->orderByDesc('id');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $services = $query->paginate($perPage);
        $data = array_map(fn (Service $s) => $this->serviceToArray($s, ['products_count' => $s->products_count ?? 0]), $services->items());

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/services – Create a service. Form-data: name (required), slug, description, image, is_active, category_id (optional).
     */
    public function store(ServiceRequest $request)
    {
        $validated = $request->validated();
        $name = $validated['name'] ?? '';
        $slug = isset($validated['slug']) && (string) $validated['slug'] !== '' ? $validated['slug'] : Str::slug($name);
        $counter = 1;
        $originalSlug = $slug;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
            'category_id' => isset($validated['category_id']) && $validated['category_id'] ? (int) $validated['category_id'] : null,
        ];

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('services', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($data['image']);
        }

        $service = Service::create($data);
        $service->load('category');

        return ApiResponse::success('Service created successfully.', $this->serviceToArray($service), 201);
    }

    /**
     * GET /api/admin/services/{id} – Get one service.
     */
    public function show(Request $request, $id)
    {
        $service = Service::with('category')->withCount('products')->findOrFail($id);
        return ApiResponse::success('Service retrieved successfully.', $this->serviceToArray($service, ['products_count' => $service->products_count ?? 0]));
    }

    /**
     * PUT/PATCH/POST /api/admin/services/{id} – Update a service.
     */
    public function update(ServiceRequest $request, $id)
    {
        $service = Service::findOrFail($id);
        $validated = $request->validated();

        $updateData = [];
        foreach (['name', 'slug', 'description', 'icon', 'sort_order', 'category_id'] as $key) {
            if (array_key_exists($key, $validated)) {
                if ($key === 'category_id') {
                    $updateData[$key] = $validated[$key] ? (int) $validated[$key] : null;
                } elseif ($key === 'sort_order') {
                    $updateData[$key] = (int) $validated[$key];
                } else {
                    $updateData[$key] = $validated[$key];
                }
            }
        }
        if (array_key_exists('is_active', $validated)) {
            $updateData['is_active'] = $request->boolean('is_active');
        }

        if (! empty($updateData['name']) && empty($updateData['slug'])) {
            $updateData['slug'] = Str::slug($updateData['name']);
        }
        if (isset($updateData['slug'])) {
            $counter = 1;
            $originalSlug = $updateData['slug'];
            while (Service::where('slug', $updateData['slug'])->where('id', '!=', $service->id)->exists()) {
                $updateData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        if ($request->boolean('image_remove')) {
            if ($service->image && Storage::disk('public')->exists($service->image)) {
                Storage::disk('public')->delete($service->image);
            }
            $updateData['image'] = null;
        } elseif ($request->hasFile('image')) {
            if ($service->image && Storage::disk('public')->exists($service->image)) {
                Storage::disk('public')->delete($service->image);
            }
            $updateData['image'] = $request->file('image')->store('services', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($updateData['image']);
        }

        $service->update($updateData);
        $service->load('category');

        return ApiResponse::success('Service updated successfully.', $this->serviceToArray($service));
    }

    /**
     * POST /api/admin/services/{id}/toggle-status – Toggle is_active (enable/disable). Same pattern as banners.
     */
    public function toggleStatus(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->is_active = ! $service->is_active;
        $service->save();

        return ApiResponse::success('Service status updated successfully.', [
            'id' => $service->id,
            'is_active' => (bool) $service->is_active,
        ]);
    }

    /**
     * DELETE /api/admin/services/{id} – Delete a service.
     */
    public function destroy(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        if ($service->products()->count() > 0) {
            return ApiResponse::error('Cannot delete service with linked products. Unlink products first.', 422);
        }
        if ($service->image && Storage::disk('public')->exists($service->image)) {
            Storage::disk('public')->delete($service->image);
        }
        $service->delete();
        return ApiResponse::success('Service deleted successfully.');
    }
}
