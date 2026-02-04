<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class CategoryController extends Controller
{
    /**
     * Parse PUT/PATCH multipart body and merge form fields + image file into request
     * (PHP does not populate $_POST/$_FILES for PUT).
     * Splits only on "\r\n--boundary" so binary image content is never truncated.
     */
    private function parsePutMultipartIntoRequest(Request $request): void
    {
        $contentType = $request->header('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'multipart/form-data')) {
            return;
        }
        if (! preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^\s;]+))/i', $contentType, $m)) {
            return;
        }
        $boundary = trim($m[1] ?? $m[2], " \t\"");
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return;
        }
        $params = [];
        $uploadedFile = null;
        // Split on line-boundary so binary content is never split; support both \r\n and \n (e.g. Postman)
        $parts = preg_split('/\r?\n--' . preg_quote($boundary, '/') . '/', $raw);
        $firstPrefix = '--' . $boundary;
        foreach ($parts as $i => $segment) {
            $part = $segment;
            if ($i === 0) {
                if ($part === '' || $part === '--') {
                    continue;
                }
                if (str_starts_with($part, $firstPrefix . "\r\n")) {
                    $part = substr($part, strlen($firstPrefix) + 2);
                } elseif (str_starts_with($part, $firstPrefix . "\n")) {
                    $part = substr($part, strlen($firstPrefix) + 1);
                }
            }
            $part = trim($part, "\r\n");
            if ($part === '' || $part === '-') {
                continue;
            }
            $headerEnd = strpos($part, "\r\n\r\n");
            if ($headerEnd === false) {
                $headerEnd = strpos($part, "\n\n");
            }
            if ($headerEnd === false) {
                continue;
            }
            $headers = substr($part, 0, $headerEnd);
            $bodyStart = $headerEnd + (str_contains($part, "\r\n\r\n") ? 4 : 2);
            $value = substr($part, $bodyStart);
            $value = preg_replace('/\r?\n$/s', '', $value);
            if (! preg_match('/name="([^"]+)"/', $headers, $nameMatch)) {
                continue;
            }
            $name = $nameMatch[1];
            $isFile = preg_match('/filename="([^"]*)"/', $headers, $fileMatch);
            if ($isFile) {
                $originalName = $fileMatch[1] !== '' ? $fileMatch[1] : 'file';
                $mimeType = null;
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $ctMatch)) {
                    $mimeType = trim($ctMatch[1]);
                }
                $tmpPath = tempnam(sys_get_temp_dir(), 'putcat_');
                if ($tmpPath !== false && file_put_contents($tmpPath, $value) !== false && $name === 'image') {
                    $uploadedFile = new UploadedFile($tmpPath, $originalName, $mimeType, \UPLOAD_ERR_OK, true);
                } else {
                    if ($tmpPath !== false) {
                        @unlink($tmpPath);
                    }
                }
                continue;
            }
            $params[$name] = $value;
        }
        if ($params !== []) {
            $request->merge($params);
        }
        if ($uploadedFile !== null) {
            $request->files->set('image', $uploadedFile);
        }
    }

    /**
     * Image compression disabled - images are stored as-is for faster uploads.
     */
    private function compressCategoryImageIfNeeded(string $relativePath): void
    {
        // Compression disabled for performance - images stored as uploaded
        return;
    }

    /**
     * Save category image from base64 string (data URL or raw base64).
     * Use when multipart file upload doesn't work (e.g. PUT body not passed by server).
     * Returns stored path or null on failure.
     */
    private function storeCategoryImageFromBase64(Request $request): ?string
    {
        $input = $request->input('image_base64');
        if (! is_string($input) || $input === '') {
            return null;
        }
        $data = $input;
        if (str_starts_with($input, 'data:')) {
            if (! preg_match('/^data:image\/(\w+);base64,(.+)$/s', $input, $m)) {
                return null;
            }
            $data = $m[2];
        }
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 10) {
            return null;
        }
        $ext = 'jpg';
        if (str_starts_with($decoded, "\x89PNG")) {
            $ext = 'png';
        } elseif (str_starts_with($decoded, 'GIF8')) {
            $ext = 'gif';
        } elseif (str_starts_with($decoded, "\xff\xd8\xff")) {
            $ext = 'jpg';
        } elseif (str_starts_with($decoded, 'RIFF') && substr($decoded, 8, 4) === 'WEBP') {
            $ext = 'webp';
        }
        $filename = 'cat_' . uniqid() . '.' . $ext;
        $path = 'categories/' . $filename;
        if (Storage::disk('public')->put($path, $decoded) === false) {
            return null;
        }
        $this->compressCategoryImageIfNeeded($path);
        return $path;
    }

    /**
     * Validate category id for API. Returns JSON error response or null if valid.
     */
    private function invalidCategoryIdResponse($id, Request $request): ?\Illuminate\Http\JsonResponse
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return null;
        }
        if ($id === null || $id === '' || (string) $id === '0') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid category id. Use a numeric id. If using Postman, set the category_id environment variable (e.g. from List Categories).',
            ], 400);
        }
        if (! is_numeric($id) || (int) $id < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid category id. Use a numeric id. Set category_id in Postman environment if needed.',
            ], 400);
        }
        if (is_string($id) && (str_contains($id, '{{') || str_contains($id, '}}'))) {
            return response()->json([
                'success' => false,
                'message' => 'Category id looks like an unresolved variable. Set category_id in your Postman environment (e.g. from List Categories).',
            ], 400);
        }
        return null;
    }

    // GET /categories - Optimized
    public function index(Request $request)
    {
        $isApi = $request->expectsJson() || $request->is('api/*');
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        
        // Optimized: skip products count for API if not needed, use orderByDesc for index scan
        $query = $isApi ? Category::query() : Category::withCount('products');
        $categories = $query->orderByDesc('id')->paginate($perPage);

        if ($isApi) {
            // Build response directly without toArray() overhead
            $data = array_map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'image' => $c->image,
                'image_url' => $c->image_url,
                'created_at' => $c->created_at,
                'updated_at' => $c->updated_at,
            ], $categories->items());
            
            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully.',
                'data' => $data,
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                ],
            ]);
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
        
        // Handle image upload (any size; auto-compress to max 2 MB)
        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('categories', 'public');
            $this->compressCategoryImageIfNeeded($validated['image']);
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
    public function show(Request $request, $id)
    {
        if ($err = $this->invalidCategoryIdResponse($id, $request)) {
            return $err;
        }
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
        if ($err = $this->invalidCategoryIdResponse($id, $request)) {
            return $err;
        }
        $category = Category::findOrFail($id);

        // Parse multipart body when: PUT/PATCH (PHP never populates $_FILES), or POST with multipart but no file yet (e.g. server didn't parse body)
        $isPutOrPatch = $request->isMethod('PUT') || $request->isMethod('PATCH');
        $isPostMultipartNoFile = $request->isMethod('POST')
            && str_contains($request->header('Content-Type', ''), 'multipart/form-data')
            && ! $request->hasFile('image');
        if ($isPutOrPatch || $isPostMultipartNoFile) {
            $this->parsePutMultipartIntoRequest($request);
        }

        // Validate (for rules/errors); then build update payload from request input so PUT form-data is applied
        $request->validate($request->rules());
        $fillable = array_flip($category->getFillable());
        $updateData = [];
        foreach (['name', 'slug', 'description'] as $key) {
            if (! array_key_exists($key, $fillable)) {
                continue;
            }
            $value = $request->input($key) ?? $request->request->get($key);
            if ($value === null && ! $request->has($key)) {
                continue;
            }
            if (is_string($value) || is_numeric($value)) {
                $updateData[$key] = $value;
            }
        }

        // Auto-generate slug from name when name is present and slug is empty
        if (! empty($updateData['name']) && (empty($updateData['slug']) ?? true)) {
            $updateData['slug'] = \Illuminate\Support\Str::slug($updateData['name']);
        }
        if (array_key_exists('slug', $updateData) && $updateData['slug'] === '') {
            $updateData['slug'] = \Illuminate\Support\Str::slug($updateData['name'] ?? $category->name);
        }

        // Ensure slug is unique (excluding current category)
        if (! empty($updateData['slug'])) {
            $counter = 1;
            $originalSlug = $updateData['slug'];
            while (Category::where('slug', $updateData['slug'])->where('id', '!=', $category->id)->exists()) {
                $updateData['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        // Handle image: file upload (multipart) OR base64 (when file upload doesn't work on server)
        $newImagePath = null;
        if ($request->hasFile('image')) {
            $newImagePath = $request->file('image')->store('categories', 'public');
        } else {
            $newImagePath = $this->storeCategoryImageFromBase64($request);
        }
        if ($newImagePath !== null) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $updateData['image'] = $newImagePath;
            $this->compressCategoryImageIfNeeded($newImagePath);
        }

        $category->update($updateData);

        // Return view redirect for web requests, JSON for API requests (includes image, image_url)
        if (request()->expectsJson() || request()->is('api/*')) {
            $category->refresh();
            // Return explicit data so image/image_url are always from DB (no cached/old URL)
            $data = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'image' => $category->image,
                'image_url' => $category->image_url,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
            return ApiResponse::success('Category updated successfully.', $data);
        }
        
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    // DELETE /categories/{id}
    public function destroy(Request $request, $id)
    {
        if ($err = $this->invalidCategoryIdResponse($id, $request)) {
            return $err;
        }
        $category = Category::findOrFail($id);

        if ($category->products()->count() > 0) {
            if (request()->expectsJson() || request()->is('api/*')) {
                return ApiResponse::error('Cannot delete category with existing products. Reassign or delete products first.', 422);
            }
            return redirect()->route('admin.categories.index')
                ->with('error', 'Cannot delete category with existing products. Please reassign or delete products first.');
        }

        if ($category->image && Storage::disk('public')->exists($category->image)) {
            Storage::disk('public')->delete($category->image);
        }
        $category->delete();

        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category deleted successfully.');
        }
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
