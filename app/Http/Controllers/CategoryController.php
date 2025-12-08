<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Category;
use App\Http\Requests\CategoryRequest;

class CategoryController extends Controller
{
    // GET /categories
    public function index()
    {
        $categories = Category::orderBy('id', 'desc')->paginate(10);
        return ApiResponse::success('Categories retrieved successfully.', $categories);
    }

    // POST /categories
    public function store(CategoryRequest $request)
    {
        $validated = $request->validated();
        
        // Auto-generate slug from name if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        
        // Ensure slug is unique
        $counter = 1;
        $originalSlug = $validated['slug'];
        while (Category::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        $category = Category::create($validated);
        return ApiResponse::success('Category created successfully.', $category, 201);
    }

    // GET /categories/{id}
    public function show($id)
    {
        $category = Category::findOrFail($id);
        return ApiResponse::success('Category retrieved successfully.', $category);
    }

    // PUT /categories/{id}
    public function update(CategoryRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $validated = $request->validated();
        
        // Auto-generate slug from name if not provided
        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        
        // Ensure slug is unique (excluding current category)
        $counter = 1;
        $originalSlug = $validated['slug'];
        while (Category::where('slug', $validated['slug'])->where('id', '!=', $category->id)->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        $category->update($validated);
        return ApiResponse::success('Category updated successfully.', $category);
    }

    // DELETE /categories/{id}
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return ApiResponse::success('Category deleted successfully.');
    }
}
