<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * List all services (using categories as services for now)
     * You can create a separate Service model if needed
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 12);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Category::query();

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
        }

        if ($categoryId) {
            $query->where('id', $categoryId);
        }

        $services = $query->paginate($perPage > 0 ? $perPage : 12);

        return ApiResponse::success('Services retrieved successfully.', $services);
    }

    /**
     * Show single service
     */
    public function show($id)
    {
        $service = Category::findOrFail($id);
        return ApiResponse::success('Service retrieved successfully.', $service);
    }

    /**
     * Get service categories
     */
    public function getCategories(Request $request)
    {
        $categories = Category::all();
        return ApiResponse::success('Categories retrieved successfully.', $categories);
    }

    /**
     * Get services by category
     */
    public function getByCategory($categoryId)
    {
        $category = Category::with('products')->findOrFail($categoryId);
        return ApiResponse::success('Services retrieved successfully.', $category);
    }
}

