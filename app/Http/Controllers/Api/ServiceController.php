<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Service;
use Illuminate\Http\Request;

/**
 * Public Services API. Services are a separate table; optional category_id for grouping.
 */
class ServiceController extends Controller
{
    /**
     * Return product payload with canonical image path/url from primary image.
     */
    private function productToApiData(Product $product): array
    {
        $rootImagePath = $product->primaryImage?->image_path ?? $product->image;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->handle ?? \Illuminate\Support\Str::slug($product->name),
            'description' => $product->description,
            'price' => (float) $product->price,
            'image' => $rootImagePath,
            'image_url' => ProductImage::buildFullUrl($rootImagePath),
            'status' => $product->status,
        ];
    }

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
     * All products of service(s) – for Services screen (cards with search & category filter).
     * GET /api/services/products?service_id=1&category_id=1&search=water&per_page=20
     * Returns products linked to at least one service; optional filter by service_id or service category, search.
     */
    public function allProductsOfService(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $serviceId = $request->query('service_id');
        $categoryId = $request->query('category_id');
        $search = $request->query('search');

        $query = Product::query()
            ->visibleInClientShop()
            ->whereHas('services');

        if ($serviceId) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
        }
        if ($categoryId) {
            $query->whereHas('services', fn ($q) => $q->where('services.category_id', $categoryId));
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        $products = $query->with(['primaryImage', 'category'])
            ->orderBy('name')
            ->paginate($perPage);

        $data = $products->getCollection()->map(function (Product $product) {
            $serviceNames = $product->services()->pluck('name')->values()->all();
            $productData = $this->productToApiData($product);
            return [
                'id' => $productData['id'],
                'name' => $productData['name'],
                'slug' => $productData['slug'],
                'description' => $productData['description'],
                'price' => $productData['price'],
                'currency' => 'AED',
                'image' => $productData['image'],
                'image_url' => $productData['image_url'],
                'category' => $product->relationLoaded('category') && $product->category
                    ? ['id' => $product->category->id, 'name' => $product->category->name]
                    : null,
                'service_names' => $serviceNames,
                'rating' => null,
            ];
        })->values()->all();

        return ApiResponse::success('Products of services retrieved successfully.', [
            'data' => $data,
            'total' => $products->total(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
            ],
        ]);
    }

    /**
     * Get products linked to a service (service ke hisab se products). Public, no auth.
     * GET /api/services/{id}/products
     */
    public function productsByService($id)
    {
        $service = Service::findOrFail($id);
        $products = $service->products()
            ->visibleInClientShop()
            ->with('primaryImage')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->productToApiData($product))
            ->values()
            ->all();

        return ApiResponse::success('Products for service retrieved successfully.', [
            'service_id' => (int) $id,
            'service_name' => $service->name,
            'products' => $products,
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
            $q->where('status', 'active')->with('primaryImage')->orderBy('name');
        }])->findOrFail($id);

        $products = $service->products
            ->map(fn (Product $product) => $this->productToApiData($product))
            ->values()
            ->all();

        $serviceData = $this->serviceToArray($service, [
            'products_count' => $service->products->count(),
            'products' => $products,
        ]);

        return ApiResponse::success('Service retrieved successfully.', $serviceData);
    }
}
