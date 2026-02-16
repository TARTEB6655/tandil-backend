<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * Public Services API. Services are a separate table; optional category_id for grouping.
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
            'created_at' => $service->created_at?->format('c'),
            'updated_at' => $service->updated_at?->format('c'),
        ], $extra);
    }

    /**
     * List services. Optional: category_id, search, per_page. Returns all (inactive show "Coming Soon").
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Service::query()->withCount('products');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $services = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);

        $data = $services->getCollection()->map(fn (Service $s) => $this->serviceToArray($s, [
            'products_count' => $s->products_count ?? 0,
            'product_names' => $s->products()->where('status', 'active')->limit(5)->pluck('name')->values()->all(),
        ]))->values()->all();

        return ApiResponse::success('Services retrieved successfully.', [
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
     * Show single service (with products linked via pivot).
     */
    public function show($id)
    {
        $service = Service::withCount('products')
            ->findOrFail($id);
        $productNames = $service->products()->where('status', 'active')->limit(5)->pluck('name')->values()->all();

        return ApiResponse::success('Service retrieved successfully.', $this->serviceToArray($service, [
            'products_count' => $service->products_count ?? 0,
            'product_names' => $productNames,
        ]));
    }

    /**
     * Get all services (no pagination). Same shape as list.
     */
    public function getCategories(Request $request)
    {
        $query = Service::query()->withCount('products');
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $services = $query->orderBy('sort_order')->orderBy('name')->get();

        $data = $services->map(fn (Service $s) => $this->serviceToArray($s, [
            'products_count' => $s->products_count ?? 0,
            'product_names' => $s->products()->where('status', 'active')->limit(5)->pluck('name')->values()->all(),
        ]))->values()->all();

        return ApiResponse::success('Services retrieved successfully.', $data);
    }

    /**
     * Get one service by id with full products list (products linked to this service via pivot).
     */
    public function getByCategory($id)
    {
        $service = Service::with(['products' => function ($q) {
            $q->where('status', 'active')->orderBy('name');
        }])->findOrFail($id);

        $products = $service->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->handle ?? \Illuminate\Support\Str::slug($product->name),
                'description' => $product->description,
                'price' => (float) $product->price,
                'image' => $product->image,
                'image_url' => $product->image_url,
                'status' => $product->status,
            ];
        })->values()->all();

        $serviceData = $this->serviceToArray($service, [
            'products_count' => $service->products->count(),
            'products' => $products,
        ]);

        return ApiResponse::success('Service retrieved successfully.', $serviceData);
    }
}
