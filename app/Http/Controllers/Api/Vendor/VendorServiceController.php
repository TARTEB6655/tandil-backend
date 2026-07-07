<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $services = Service::with('category')
            ->vendorAssignable()
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
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

    public function store(Request $request): JsonResponse
    {
        return ApiResponse::error('Vendors cannot create services. Use platform services when adding products.', 403);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $service = Service::with('category')->vendorAssignable()->where('id', $id)->first();
        if ($service === null) {
            return ApiResponse::error('Service not found.', 404);
        }

        return ApiResponse::success('Service retrieved.', ['service' => $this->toArray($service)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return ApiResponse::error('Vendors cannot update services. Use platform services when adding products.', 403);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return ApiResponse::error('Vendors cannot delete services.', 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Service $service): array
    {
        return [
            'id' => $service->id,
            'vendor_id' => $service->vendor_id,
            'is_platform' => true,
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
