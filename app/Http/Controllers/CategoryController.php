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
        $raw = $request->getContent();
        if ($raw === '' || $raw === false) {
            return;
        }
        $params = [];
        $uploadedFile = null;
        $parts = array_slice(explode('--' . $boundary, $raw), 1, -1);
        foreach ($parts as $part) {
            $part = trim($part, "\r\n");
            if ($part === '' || $part === '--') {
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
            $value = preg_replace('/\r?\n--\s*$/', '', $value);
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

    /** Max size in bytes for stored category images; larger files are compressed automatically. */
    private const CATEGORY_IMAGE_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * Compress a stored category image to at most 2 MB if larger. Uses GD. No-op if GD missing or file already small.
     *
     * @param string $relativePath Path under storage/app/public (e.g. categories/abc.jpg)
     */
    private function compressCategoryImageIfNeeded(string $relativePath): void
    {
        if (! extension_loaded('gd')) {
            return;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return;
        }
        $fullPath = $disk->path($relativePath);
        if (! is_file($fullPath) || filesize($fullPath) <= self::CATEGORY_IMAGE_MAX_BYTES) {
            return;
        }
        $info = @getimagesize($fullPath);
        if ($info === false || ! isset($info[0], $info[1], $info[2])) {
            return;
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = $info[2];
        $img = null;
        if ($type === \IMAGETYPE_JPEG) {
            $img = @imagecreatefromjpeg($fullPath);
        } elseif ($type === \IMAGETYPE_PNG) {
            $img = @imagecreatefrompng($fullPath);
        } elseif ($type === \IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($fullPath);
        }
        if ($img === false || $img === null) {
            return;
        }
        $maxBytes = self::CATEGORY_IMAGE_MAX_BYTES;
        $currentSize = filesize($fullPath);
        $scale = 1.0;
        if ($currentSize > $maxBytes) {
            $ratio = sqrt($maxBytes / $currentSize);
            $scale = max(0.1, min(1.0, $ratio * 0.95));
        }
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);
        if ($newW < 1) {
            $newW = 1;
        }
        if ($newH < 1) {
            $newH = 1;
        }
        $resized = imagecreatetruecolor($newW, $newH);
        if ($resized === false) {
            imagedestroy($img);
            return;
        }
        if ($type === \IMAGETYPE_PNG) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($img);
        $quality = 88;
        $ext = strtolower(pathinfo($fullPath, \PATHINFO_EXTENSION));
        if ($type === \IMAGETYPE_JPEG || $ext === 'jpg' || $ext === 'jpeg') {
            while ($quality >= 50) {
                imagejpeg($resized, $fullPath, $quality);
                if (is_file($fullPath) && filesize($fullPath) <= $maxBytes) {
                    break;
                }
                $quality -= 10;
            }
        } elseif ($type === \IMAGETYPE_PNG) {
            imagepng($resized, $fullPath, 8);
        } elseif ($type === \IMAGETYPE_WEBP && function_exists('imagewebp')) {
            while ($quality >= 50) {
                imagewebp($resized, $fullPath, $quality);
                if (is_file($fullPath) && filesize($fullPath) <= $maxBytes) {
                    break;
                }
                $quality -= 10;
            }
        } else {
            imagejpeg($resized, $fullPath, 85);
        }
        imagedestroy($resized);
        if (is_file($fullPath) && filesize($fullPath) > $maxBytes && ($newW > 400 || $newH > 400)) {
            $this->compressCategoryImageIfNeeded($relativePath);
        }
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

        // PHP does not populate $_POST/$_FILES for PUT; parse multipart so fields + image work
        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
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

        // Handle image upload: delete old image if new one provided (any size; auto-compress to max 2 MB)
        if ($request->hasFile('image')) {
            if ($category->image && Storage::disk('public')->exists($category->image)) {
                Storage::disk('public')->delete($category->image);
            }
            $updateData['image'] = $request->file('image')->store('categories', 'public');
            $this->compressCategoryImageIfNeeded($updateData['image']);
        }

        $category->update($updateData);

        // Return view redirect for web requests, JSON for API requests (includes image, image_url)
        if (request()->expectsJson() || request()->is('api/*')) {
            $category->refresh();
            return ApiResponse::success('Category updated successfully.', $category);
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
