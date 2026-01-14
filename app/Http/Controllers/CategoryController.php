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
        $categories = Category::withCount('products')->orderBy('id', 'desc')->paginate(10);
        
        // Return view for web requests, JSON for API requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Categories retrieved successfully.', $categories);
        }
        
        return view('admin.categories.index', compact('categories'));
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
        
        // Return view redirect for web requests, JSON for API requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category created successfully.', $category, 201);
        }
        
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    // GET /categories/create
    public function create()
    {
        // Return JSON error for API requests (create is not used in API)
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::error('Use POST /api/categories to create a category.', 405);
        }
        
        return view('admin.categories.create');
    }

    // GET /categories/{id}
    public function show($id)
    {
        $category = Category::findOrFail($id);
        
        // Return view for web requests, JSON for API requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category retrieved successfully.', $category);
        }
        
        return view('admin.categories.show', compact('category'));
    }

    // GET /categories/{id}/edit
    public function edit($id)
    {
        $category = Category::findOrFail($id);
        
        // Return JSON error for API requests (edit is not used in API)
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::error('Use PUT /api/categories/{id} to update a category.', 405);
        }
        
        return view('admin.categories.edit', compact('category'));
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
        
        // Return view redirect for web requests, JSON for API requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category updated successfully.', $category);
        }
        
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    // DELETE /categories/{id}
    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        
        // Return view redirect for web requests, JSON for API requests
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category deleted successfully.');
        }
        
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
