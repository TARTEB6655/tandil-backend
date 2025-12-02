<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;

class CategoryController extends Controller
{
    // GET /categories
    public function index()
    {
        return CategoryResource::collection(
            Category::orderBy('id', 'desc')->paginate(10)
        );
    }

    // POST /categories
    public function store(CategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return new CategoryResource($category);
    }

    // GET /categories/{id}
    public function show(Category $category)
    {
        return new CategoryResource($category);
    }

    // PUT /categories/{id}
    public function update(CategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    // DELETE /categories/{id}
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully',
        ]);
    }
}
