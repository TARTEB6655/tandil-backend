<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class CategoryController extends Controller
{
    /**
     * Parse multipart/form-data body and merge form fields + image file into the request.
     * Mirrors the product controller parser (same split, boundary, and file handling) so
     * PUT with form-data works the same for category image update as for product.
     */
    private function parsePutMultipartIntoRequest(Request $request): void
    {
        $contentType = $request->header('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'multipart/form-data')) {
            return;
        }
        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/', $contentType, $m)) {
            return;
        }
        $boundary = trim($m[1] ?? $m[2]);
        // Prefer body cached by CachePutRequestBody (first read of php://input)
        $raw = $request->attributes->get('_put_multipart_raw');
        if ($raw === null) {
            $raw = $request->getContent();
        }
        if ($raw === '' || $raw === false || ! is_string($raw)) {
            return;
        }
        $params = [];
        $uploadedFile = null;
        $lineDelimiter = "\r\n--" . $boundary;
        $parts = explode($lineDelimiter, $raw);
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
                // Trim trailing multipart boundary so it is not written into the file
                $trailingBoundary = "\r\n--" . $boundary . "--";
                if (str_ends_with($value, $trailingBoundary)) {
                    $value = substr($value, 0, -strlen($trailingBoundary));
                }
                $trailingBoundaryLf = "\n--" . $boundary . "--";
                if (str_ends_with($value, $trailingBoundaryLf)) {
                    $value = substr($value, 0, -strlen($trailingBoundaryLf));
                }
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
     * Compress image if over 5 MB (all image uploads in project).
     */
    private function compressCategoryImageIfNeeded(string $relativePath): void
    {
        ImageCompressionService::compressIfNeededFromPublicPath($relativePath);
    }

    /**
     * Build full image URL from path (uses request host when available so live server returns correct URL).
     */
    private function buildCategoryImageUrl(?string $path): ?string
    {
        if (! $path || ! is_string($path)) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/') . '/media/' . $path;
        }
        return asset('media/' . $path);
    }

    /**
     * Single place for category API response shape. Use in list, create, show, update – easy to update.
     * Returns: id, name, slug, description, image, image_url, created_at, updated_at.
     * When $imagePathOverride is set (e.g. after update), use it so response always shows the new image URL.
     */
    private function categoryToApiData(Category $category, ?string $imagePathOverride = null): array
    {
        $imagePath = $imagePathOverride !== null ? $imagePathOverride : $category->image;
        $isActive = isset($category->is_active) ? (bool) $category->is_active : true;
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $imagePath,
            'image_url' => $this->buildCategoryImageUrl($imagePath),
            'is_active' => $isActive,
            'coming_soon' => ! $isActive,
            'shipping_amount' => $category->shipping_amount !== null ? round((float) $category->shipping_amount, 2) : null,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ];
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
            $data = array_map(fn (Category $c) => $this->categoryToApiData($c), $categories->items());
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

    // POST /categories – create with minimal data (only name required); slug/description/image optional
    public function store(CategoryRequest $request)
    {
        $validated = $request->validated();
        $name = $validated['name'] ?? '';
        $slug = isset($validated['slug']) && (string) $validated['slug'] !== '' ? $validated['slug'] : \Illuminate\Support\Str::slug($name);
        $description = array_key_exists('description', $validated) ? $validated['description'] : null;
        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            $this->compressCategoryImageIfNeeded($imagePath);
        }
        // Ensure slug is unique
        $counter = 1;
        $originalSlug = $slug;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        $shippingAmount = $request->filled('shipping_amount')
            ? round(max(0, (float) $request->input('shipping_amount')), 2)
            : null;

        $category = Category::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'image' => $imagePath,
            'is_active' => $isActive,
            'shipping_amount' => $shippingAmount,
        ]);
        
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category created successfully.', $this->categoryToApiData($category), 201);
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
        
        if (request()->expectsJson() || request()->is('api/*')) {
            return ApiResponse::success('Category retrieved successfully.', $this->categoryToApiData($category));
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

    /**
     * Update category. Supports PUT and POST (route: put|post).
     * Image: multipart form-data only — send "image" file, or "image_remove": true (form field or JSON) to clear.
     */
    public function update(CategoryRequest $request, $id)
    {
        if ($err = $this->invalidCategoryIdResponse($id, $request)) {
            return $err;
        }
        $category = Category::findOrFail($id);

        // Always parse multipart body for PUT/PATCH (PHP never populates $_FILES) and for POST with multipart
        // so that image is available even when server does not populate $_FILES (e.g. some hosts/proxies).
        $isMultipart = str_contains($request->header('Content-Type', ''), 'multipart/form-data');
        if ($isMultipart && ($request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('POST'))) {
            $this->parsePutMultipartIntoRequest($request);
        }

        // Validate (for rules/errors); then build update payload from request input so PUT form-data is applied
        $request->validate($request->rules());
        $fillable = array_flip($category->getFillable());
        $updateData = [];
        foreach (['name', 'slug', 'description', 'is_active', 'shipping_amount'] as $key) {
            if (! array_key_exists($key, $fillable)) {
                continue;
            }
            if ($key === 'is_active') {
                if ($request->has($key)) {
                    $updateData[$key] = $request->boolean($key);
                }
                continue;
            }
            if ($key === 'shipping_amount') {
                if ($request->has($key)) {
                    $raw = $request->input($key);
                    $updateData[$key] = ($raw === '' || $raw === null) ? null : round(max(0, (float) $raw), 2);
                }
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
        
        // Handle image: remove (image_remove=true), or new file via multipart only; otherwise leave existing image
        $newImagePath = null;
        if ($request->boolean('image_remove')) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $updateData['image'] = null;
        } else {
            if ($request->hasFile('image')) {
                $newImagePath = $request->file('image')->store('categories', 'public');
                if ($category->image && Storage::disk('public')->exists($category->image)) {
                    Storage::disk('public')->delete($category->image);
                }
                $updateData['image'] = $newImagePath;
                $this->compressCategoryImageIfNeeded($newImagePath);
            }
        }

        $category->update($updateData);

        if (request()->expectsJson() || request()->is('api/*')) {
            $category->refresh();
            $responseImagePath = isset($updateData['image']) ? $updateData['image'] : $category->image;
            $data = $this->categoryToApiData($category, $responseImagePath);
            $imageNotReceived = $request->isMethod('PUT') && $isMultipart && ! $request->hasFile('image') && ! $request->boolean('image_remove') && $newImagePath === null;
            $imageWasUpdated = $newImagePath !== null || (isset($updateData['image']) && $updateData['image'] === null && $request->boolean('image_remove'));
            $response = [
                'success' => true,
                'message' => $imageNotReceived
                    ? 'Category updated. Image was not changed — use POST (not PUT) with form-data to update the image.'
                    : 'Category updated successfully.',
                'data' => $data,
            ];
            if ($imageNotReceived) {
                $response['image_updated'] = false;
                $response['hint'] = 'In Postman: change method to POST and send the same form-data (name, slug, description, image file). The API accepts both PUT and POST for update.';
            } elseif ($imageWasUpdated) {
                $response['image_updated'] = true;
            }
            return response()->json($response);
        }
        
        return redirect()->route('admin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    // POST /categories/{id}/toggle-status – Toggle is_active (enable/disable). Same pattern as banners.
    public function toggleStatus(Request $request, $id)
    {
        if ($err = $this->invalidCategoryIdResponse($id, $request)) {
            return $err;
        }
        $category = Category::findOrFail($id);
        $category->is_active = ! $category->is_active;
        $category->save();

        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponse::success('Category status updated successfully.', [
                'id' => $category->id,
                'is_active' => (bool) $category->is_active,
            ]);
        }
        return redirect()->back()->with('success', 'Category status updated.');
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
