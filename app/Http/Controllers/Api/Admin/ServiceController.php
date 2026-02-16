<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CategoryController;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;

/**
 * Dedicated Services API (CRUD). Services = Categories in the database.
 * All routes under /api/admin/services. Auth: Bearer token, admin role.
 */
class ServiceController extends Controller
{
    private function serviceToArray(Category $category, array $extra = []): array
    {
        return array_merge([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'image_url' => $category->image_url,
            'is_active' => (bool) ($category->is_active ?? true),
            'coming_soon' => (bool) $category->coming_soon,
            'created_at' => $category->created_at?->format('c'),
            'updated_at' => $category->updated_at?->format('c'),
        ], $extra);
    }

    /**
     * GET /api/admin/services – List all services (paginated).
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $categories = Category::withCount('products')->orderByDesc('id')->paginate($perPage);
        $data = array_map(fn (Category $c) => $this->serviceToArray($c, ['products_count' => $c->products_count ?? 0]), $categories->items());

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved successfully.',
            'data' => $data,
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * POST /api/admin/services – Create a service. Form-data: name (required), slug, description, image, is_active.
     */
    public function store(CategoryRequest $request)
    {
        $response = app(CategoryController::class)->store($request);
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $payload = $response->getData(true);
            $payload['message'] = 'Service created successfully.';
            return response()->json($payload, $response->getStatusCode());
        }
        return $response;
    }

    /**
     * GET /api/admin/services/{id} – Get one service.
     */
    public function show(Request $request, $id)
    {
        $category = Category::withCount('products')->findOrFail($id);
        return ApiResponse::success('Service retrieved successfully.', $this->serviceToArray($category, ['products_count' => $category->products_count ?? 0]));
    }

    /**
     * PUT/PATCH/POST /api/admin/services/{id} – Update a service. Form-data or JSON: name, slug, description, image, image_remove, is_active.
     */
    public function update(CategoryRequest $request, $id)
    {
        $response = app(CategoryController::class)->update($request, $id);
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $payload = $response->getData(true);
            $payload['message'] = isset($payload['message']) && str_contains($payload['message'], 'Category')
                ? str_replace('Category', 'Service', $payload['message'])
                : 'Service updated successfully.';
            if (isset($payload['hint'])) {
                $payload['hint'] = str_replace('category', 'service', $payload['hint']);
            }
            return response()->json($payload, $response->getStatusCode());
        }
        return $response;
    }

    /**
     * DELETE /api/admin/services/{id} – Delete a service (fails if it has products).
     */
    public function destroy(Request $request, $id)
    {
        $response = app(CategoryController::class)->destroy($request, $id);
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $payload = $response->getData(true);
            if (isset($payload['message'])) {
                $payload['message'] = str_replace('Category', 'Service', $payload['message']);
                $payload['message'] = str_replace('category', 'service', $payload['message']);
            }
            return response()->json($payload, $response->getStatusCode());
        }
        return $response;
    }
}
