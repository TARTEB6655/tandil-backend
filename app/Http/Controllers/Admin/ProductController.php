<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

class ProductController extends Controller
{
    /** Product API allowed fields only (name, description, price, stock, status, category_id, weight_unit, sku, handle). No extra fields. */
    private const PRODUCT_API_FIELDS = [
        'name', 'description', 'price', 'stock', 'status', 'category_id', 'weight_unit', 'sku', 'handle',
    ];

    /** Response keys for product API (allowed fields + id, image, image_url, images, primary_image, category, timestamps). */
    private const PRODUCT_API_RESPONSE_KEYS = [
        'id', 'name', 'description', 'price', 'stock', 'status', 'category_id', 'weight_unit', 'sku', 'handle',
        'image', 'image_url', 'images', 'primary_image', 'category', 'created_at', 'updated_at',
    ];

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Return product data with only allowed API fields (no extra fields in response).
     */
    private function productToApiData(Product $product): array
    {
        $product->loadMissing(['category', 'images', 'primaryImage']);
        $data = $product->toArray();
        return array_intersect_key($data, array_flip(self::PRODUCT_API_RESPONSE_KEYS));
    }

    /**
     * Validate product id from URL. Returns JSON error response or null if valid.
     */
    private function invalidProductIdResponse($id, Request $request): ?\Illuminate\Http\JsonResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            if ($id === null || $id === '' || (string) $id === '0') {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid product id. Use a numeric id (e.g. 1). If using Postman, set the product_id environment variable to an existing product id.',
                ], 400);
            }
            if (! is_numeric($id) || (int) $id < 1) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid product id. Use a numeric id (e.g. 1). If using Postman, set the product_id environment variable.',
                ], 400);
            }
            if (is_string($id) && (str_contains($id, '{{') || str_contains($id, '}}'))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Product id looks like an unresolved variable. Set product_id in your Postman environment (e.g. from the List Products or Add Product response).',
                ], 400);
            }
        }
        return null;
    }

    /**
     * PHP does not populate $_POST or $_FILES for PUT/PATCH requests. Parse multipart/form-data
     * body and merge form fields + file uploads into the request so update() works with both.
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
        $filesSingle = [];   // 'image' => UploadedFile
        $filesMulti = [];    // 'images' => [UploadedFile, ...]
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
                $tmpPath = tempnam(sys_get_temp_dir(), 'put_');
                if ($tmpPath !== false && file_put_contents($tmpPath, $value) !== false) {
                    $uploaded = new UploadedFile($tmpPath, $originalName, $mimeType, \UPLOAD_ERR_OK, true);
                    if ($name === 'image') {
                        $filesSingle['image'] = $uploaded;
                    } elseif ($name === 'images' || $name === 'images[]') {
                        $filesMulti['images'] = $filesMulti['images'] ?? [];
                        $filesMulti['images'][] = $uploaded;
                    }
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
        foreach ($filesSingle as $key => $file) {
            $request->files->set($key, $file);
        }
        foreach ($filesMulti as $key => $fileArray) {
            $request->files->set($key, $fileArray);
            // Same as Add Product: support both 'images' and 'images[]' so single or multiple files work
            if ($key === 'images') {
                $request->files->set('images[]', $fileArray);
            }
        }
    }

    /** Max size in bytes for stored product images; larger files are compressed automatically. */
    private const PRODUCT_IMAGE_MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    /**
     * Compress a stored product image to at most 2 MB if larger. Uses GD. No-op if GD missing or file already small.
     *
     * @param string $relativePath Path under storage/app/public (e.g. products/abc.jpg)
     */
    private function compressProductImageIfNeeded(string $relativePath): void
    {
        if (! extension_loaded('gd')) {
            return;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return;
        }
        $fullPath = $disk->path($relativePath);
        if (! is_file($fullPath) || filesize($fullPath) <= self::PRODUCT_IMAGE_MAX_BYTES) {
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
        if ($type === \IMAGETYPE_JPEG || $type === \IMAGETYPE_JPEG) {
            $img = @imagecreatefromjpeg($fullPath);
        } elseif ($type === \IMAGETYPE_PNG) {
            $img = @imagecreatefrompng($fullPath);
        } elseif ($type === \IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($fullPath);
        }
        if ($img === false || $img === null) {
            return;
        }
        $maxBytes = self::PRODUCT_IMAGE_MAX_BYTES;
        $scale = 1.0;
        $currentSize = filesize($fullPath);
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
        $saved = false;
        $ext = strtolower(pathinfo($fullPath, \PATHINFO_EXTENSION));
        if ($type === \IMAGETYPE_JPEG || $type === \IMAGETYPE_JPEG || $ext === 'jpg' || $ext === 'jpeg') {
            while ($quality >= 50) {
                $saved = imagejpeg($resized, $fullPath, $quality);
                if ($saved && is_file($fullPath) && filesize($fullPath) <= $maxBytes) {
                    break;
                }
                $quality -= 10;
            }
        } elseif ($type === \IMAGETYPE_PNG) {
            $saved = imagepng($resized, $fullPath, 8);
        } elseif ($type === \IMAGETYPE_WEBP && function_exists('imagewebp')) {
            while ($quality >= 50) {
                $saved = imagewebp($resized, $fullPath, $quality);
                if ($saved && is_file($fullPath) && filesize($fullPath) <= $maxBytes) {
                    break;
                }
                $quality -= 10;
            }
        } else {
            $saved = imagejpeg($resized, $fullPath, 85);
        }
        imagedestroy($resized);
        // If still over limit (e.g. large PNG), one more compression pass
        if (is_file($fullPath) && filesize($fullPath) > $maxBytes && ($newW > 400 || $newH > 400)) {
            $this->compressProductImageIfNeeded($relativePath);
        }
    }

    /**
     * List products (with search, category filter, pagination).
     */
    public function index(Request $request)
    {
        $search     = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Product::with(['category', 'images', 'primaryImage']);

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Apply filter tabs
        $filter = $request->get('filter', 'all');
        if ($filter === 'active') {
            $query->where('stock', '>', 0);
        } elseif ($filter === 'draft') {
            // You can add a status field to products table later
            $query->where('stock', '<=', 0);
        } elseif ($filter === 'archived') {
            // You can add an archived field to products table later
            $query->where('stock', '<=', 0);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        $categories = \App\Models\Category::all();

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => array_map(fn (Product $p) => $this->productToApiData($p), $products->items()),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        }

        return view('admin.products.index', compact('products', 'categories'));
    }

    /**
     * Create a product.
     * Same endpoint for (1) JSON body: product fields + optional image_urls (array of URLs).
     * (2) Multipart/form-data: product fields as form fields + image files in images[] (or image for single)
     *     + optional image_urls (JSON string) or image_url[] (repeated) to merge with file uploads.
     * Auth: Authorization: Bearer {{admin_token}}.
     */
    public function store(Request $request)
    {
        // Capture category_id from form-data early (multipart + files can sometimes hide it from input())
        $categoryIdRaw = $request->input('category_id') ?? $request->request->get('category_id');
        if ($categoryIdRaw !== null && $categoryIdRaw !== '') {
            $request->merge(['category_id' => is_array($categoryIdRaw) ? ($categoryIdRaw[0] ?? null) : $categoryIdRaw]);
        } elseif ($request->has('category_id') && $request->category_id === '') {
            $request->merge(['category_id' => null]);
        }

        // Normalize image_urls for multipart: Option 1 = JSON string; Option 2 = repeated image_url[]
        $imageUrls = null;
        if ($request->has('image_urls')) {
            $v = $request->input('image_urls');
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $imageUrls = is_array($decoded) ? array_values($decoded) : null;
            } elseif (is_array($v)) {
                $imageUrls = array_values($v);
            }
        }
        if ($imageUrls === null && $request->has('image_url')) {
            $imageUrls = array_values((array) $request->input('image_url'));
        }
        if ($imageUrls !== null) {
            $request->merge(['image_urls' => $imageUrls]);
        }

        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'status'      => 'nullable|in:draft,active,archived',
            'category_id' => 'nullable|integer',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'sku'         => 'nullable|string|max:255|unique:products,sku',
            'handle'      => 'nullable|string|max:255|unique:products,handle',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'main_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp',
            'image_urls'  => 'nullable|array',
            'image_urls.*'=> 'nullable|string|url',
        ], [
            'handle.unique' => 'The handle has already been taken. Please use a different handle or leave it blank to auto-generate.',
            'sku.unique'    => 'The SKU has already been taken. Please use a unique SKU.',
        ]);

        // Build create data from allowed fields only (no extra fields)
        $createData = [];
        foreach (self::PRODUCT_API_FIELDS as $key) {
            $value = $request->input($key) ?? $request->request->get($key) ?? ($validated[$key] ?? null);
            if ($value instanceof \Illuminate\Http\UploadedFile || is_array($value)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $createData[$key] = $value;
            }
        }
        $createData['name'] = $createData['name'] ?? $validated['name'] ?? '';
        $createData['price'] = $createData['price'] ?? $validated['price'] ?? 0;
        $createData['status'] = $createData['status'] ?? $validated['status'] ?? 'draft';
        $createData['weight_unit'] = $createData['weight_unit'] ?? $validated['weight_unit'] ?? 'kg';
        $createData['stock'] = $createData['stock'] ?? $validated['stock'] ?? 0;
        if (empty($createData['handle']) && ! empty($createData['name'])) {
            $createData['handle'] = Str::slug($createData['name']);
            $counter = 1;
            $original = $createData['handle'];
            while (Product::where('handle', $createData['handle'])->exists()) {
                $createData['handle'] = $original . '-' . $counter++;
            }
        }
        $rawCategoryId = $request->input('category_id') ?? $request->request->get('category_id') ?? ($validated['category_id'] ?? null);
        if (is_array($rawCategoryId)) {
            $rawCategoryId = $rawCategoryId[0] ?? null;
        }
        if ($rawCategoryId !== null && $rawCategoryId !== '' && is_numeric($rawCategoryId)) {
            $cid = (int) $rawCategoryId;
            $createData['category_id'] = Category::find($cid) ? $cid : null;
        } else {
            $createData['category_id'] = null;
        }

        // SQLite only: column is NOT NULL; assign first category or Uncategorized when none sent
        if ($createData['category_id'] === null && \Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite') {
            $firstCategory = Category::orderBy('id')->first();
            if ($firstCategory) {
                $createData['category_id'] = $firstCategory->id;
            } else {
                $uncategorized = Category::firstOrCreate(
                    ['slug' => 'uncategorized'],
                    ['name' => 'Uncategorized', 'description' => null]
                );
                $createData['category_id'] = $uncategorized->id;
            }
        }

        try {
            $product = Product::create($createData);
        } catch (\Illuminate\Database\QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (! str_contains($msg, 'unique')) {
                throw $e;
            }
            $errors = [];
            // Try to detect which column from exception message (e.g. "products_handle_unique" or "products.handle")
            if (str_contains($msg, 'handle')) {
                $errors['handle'] = ['The handle has already been taken. Please use a different handle or leave it blank to auto-generate.'];
            }
            if (str_contains($msg, 'sku')) {
                $errors['sku'] = ['The SKU has already been taken. Please use a unique SKU.'];
            }
            // Fallback: check DB for which one exists
            if ($errors === []) {
                if (! empty($createData['handle']) && Product::where('handle', $createData['handle'])->exists()) {
                    $errors['handle'] = ['The handle has already been taken. Please use a different handle or leave it blank to auto-generate.'];
                }
                if (! empty($createData['sku']) && Product::where('sku', $createData['sku'])->exists()) {
                    $errors['sku'] = ['The SKU has already been taken. Please use a unique SKU.'];
                }
            }
            if ($errors === []) {
                $errors['handle'] = ['A product with this handle or SKU already exists. Please use a unique handle and a unique SKU.'];
                $errors['sku'] = ['A product with this handle or SKU already exists. Please use a unique handle and a unique SKU.'];
            }
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Handle or SKU may already exist.',
                'errors'  => $errors,
            ], 422);
        }

        // Handle images: main_image (or image) = primary; images[] = extra only
        $mainFile = null;
        if ($request->hasFile('main_image')) {
            $f = $request->file('main_image');
            if ($f && $f->isValid()) {
                $mainFile = $f;
            }
        }
        if ($mainFile === null && $request->hasFile('image')) {
            $f = $request->file('image');
            if ($f && $f->isValid()) {
                $mainFile = $f;
            }
        }
        $extraFiles = [];
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $extraFiles = is_array($files) ? $files : [$files];
        }
        if ($request->hasFile('images[]')) {
            $files = $request->file('images[]');
            $extraFiles = array_merge($extraFiles, is_array($files) ? $files : [$files]);
        }
        $extraFiles = array_values(array_filter($extraFiles, function ($f) {
            return $f && $f->isValid();
        }));

        $sortOrder = 0;
        if ($mainFile !== null) {
            $imagePath = $mainFile->store('products', 'public');
            $this->compressProductImageIfNeeded($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
                'is_primary' => true,
            ]);
            $product->update(['image' => $imagePath]);
        }
        foreach ($extraFiles as $image) {
            $imagePath = $image->store('products', 'public');
            $this->compressProductImageIfNeeded($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
                'is_primary' => false,
            ]);
        }
        // If no main file but we have extras, first extra becomes primary (backward compat)
        if ($mainFile === null && $extraFiles !== []) {
            $first = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
            if ($first) {
                $first->update(['is_primary' => true]);
                $product->update(['image' => $first->image_path]);
            }
        }

        // Merge image URLs (from JSON body or multipart image_urls / image_url[])
        $primaryAlreadySet = ($mainFile !== null || $extraFiles !== []);
        if ($request->has('image_urls') && is_array($request->image_urls)) {
            foreach ($request->image_urls as $imageUrl) {
                if (is_string($imageUrl) && $imageUrl !== '') {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imageUrl,
                        'sort_order' => $sortOrder,
                        'is_primary' => ! $primaryAlreadySet && $sortOrder === 0,
                    ]);
                    $sortOrder++;
                }
            }
        }

        // Ensure product.image is set from primary when we only had image_urls
        $firstImage = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first()
            ?? ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
        if ($firstImage && ! $product->image) {
            $product->update(['image' => $firstImage->image_path]);
        }

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            $product->load(['category', 'images', 'primaryImage']);
            return response()->json([
                'status' => true,
                'message' => 'Product created successfully.',
                'data' => $this->productToApiData($product),
            ], 201);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Show form for editing product.
     */
    public function edit($id)
    {
        $product = Product::with(['category', 'images'])->findOrFail($id);
        $categories = \App\Models\Category::all();
        return view('admin.products.edit', compact('product', 'categories'));
    }

    /**
     * Show a single product.
     */
    public function show(Request $request, $id)
    {
        if ($err = $this->invalidProductIdResponse($id, $request)) {
            return $err;
        }
        $product = Product::with(['category', 'images', 'primaryImage'])->findOrFail($id);

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product retrieved successfully.',
                'data' => $this->productToApiData($product),
            ]);
        }
        
        return view('admin.products.show', compact('product'));
    }

    /**
     * Show form for creating a product.
     */
    public function create()
    {
        $categories = \App\Models\Category::all();
        return view('admin.products.create', compact('categories'));
    }

    /**
     * Update product.
     * Same as create: accepts JSON body or multipart/form-data with all product fields + image (single)
     * or images[] (multiple). Partial update: only sent fields are updated. Auth: Bearer {{admin_token}}.
     */
    public function update(Request $request, $id)
    {
        if ($err = $this->invalidProductIdResponse($id, $request)) {
            return $err;
        }
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // PHP does not populate $_POST for PUT/PATCH; parse multipart body so input() works
        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $this->parsePutMultipartIntoRequest($request);
        }

        // Capture category_id from form-data early (multipart + files can hide it)
        $categoryIdRaw = $request->input('category_id') ?? $request->request->get('category_id');
        if ($categoryIdRaw !== null && $categoryIdRaw !== '') {
            $request->merge(['category_id' => is_array($categoryIdRaw) ? ($categoryIdRaw[0] ?? null) : $categoryIdRaw]);
        } elseif ($request->has('category_id') && $request->category_id === '') {
            $request->merge(['category_id' => null]);
        }

        // Normalize image_urls for multipart (optional on update)
        $imageUrls = null;
        if ($request->has('image_urls')) {
            $v = $request->input('image_urls');
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $imageUrls = is_array($decoded) ? array_values($decoded) : null;
            } elseif (is_array($v)) {
                $imageUrls = array_values($v);
            }
        }
        if ($imageUrls === null && $request->has('image_url')) {
            $imageUrls = array_values((array) $request->input('image_url'));
        }
        if ($imageUrls !== null) {
            $request->merge(['image_urls' => $imageUrls]);
        }

        $validated = $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'stock'       => 'nullable|integer|min:0',
            'status'      => 'nullable|in:draft,active,archived',
            'category_id' => 'nullable|integer',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'sku'         => 'nullable|string|max:255|unique:products,sku,' . $id,
            'handle'      => 'nullable|string|max:255|unique:products,handle,' . $id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'main_image'  => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp',
            'image_urls'  => 'nullable|array',
            'image_urls.*'=> 'nullable|string|url',
        ], [
            'handle.unique' => 'The handle has already been taken.',
            'sku.unique'    => 'The SKU has already been taken.',
        ]);

        // Build update payload from allowed fields only (no extra fields)
        $updateData = [];
        foreach (self::PRODUCT_API_FIELDS as $key) {
            $value = $request->input($key) ?? $request->request->get($key);
            if ($value === null && ! $request->has($key) && ! $request->request->has($key)) {
                continue;
            }
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }
            $updateData[$key] = $value;
        }

        // category_id: normalize (form-data sends string; empty = null)
        $rawCategoryId = $request->input('category_id') ?? $request->request->get('category_id');
        if (is_array($rawCategoryId)) {
            $rawCategoryId = $rawCategoryId[0] ?? null;
        }
        if ($request->has('category_id')) {
            if ($rawCategoryId !== null && $rawCategoryId !== '' && is_numeric($rawCategoryId)) {
                $cid = (int) $rawCategoryId;
                $updateData['category_id'] = Category::find($cid) ? $cid : null;
            } else {
                $updateData['category_id'] = null;
            }
        }

        // main_image = primary only; images[] = extra only. Backward compat: image or images[] without main_image = replace all (first = primary)
        $mainFile = null;
        if ($request->hasFile('main_image')) {
            $f = $request->file('main_image');
            if ($f && $f->isValid()) {
                $mainFile = $f;
            }
        }
        $extraFiles = [];
        if ($request->hasFile('image') && $mainFile === null) {
            $f = $request->file('image');
            if ($f && $f->isValid()) {
                $extraFiles[] = $f;
            }
        }
        if ($request->hasFile('images')) {
            $files = $request->file('images');
            $extraFiles = array_merge($extraFiles, is_array($files) ? $files : [$files]);
        }
        if ($request->hasFile('images[]')) {
            $files = $request->file('images[]');
            $extraFiles = array_merge($extraFiles, is_array($files) ? $files : [$files]);
        }
        $extraFiles = array_values(array_filter($extraFiles, function ($f) {
            return $f && $f->isValid();
        }));

        if ($mainFile !== null) {
            // Replace only the primary image; keep existing extra images unless images[] also sent
            $oldPrimary = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            if ($oldPrimary && $oldPrimary->image_path && ! str_starts_with($oldPrimary->image_path, 'http') && Storage::disk('public')->exists($oldPrimary->image_path)) {
                Storage::disk('public')->delete($oldPrimary->image_path);
            }
            if ($oldPrimary) {
                $oldPrimary->delete();
            }
            $imagePath = $mainFile->store('products', 'public');
            $this->compressProductImageIfNeeded($imagePath);
            $maxOrder = (int) ProductImage::where('product_id', $product->id)->max('sort_order');
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => 0,
                'is_primary' => true,
            ]);
            $updateData['image'] = $imagePath;
        }

        if ($extraFiles !== []) {
            // Replace all non-primary images with new extra files (or set first as primary if no main_image was sent)
            $existingNonPrimary = ProductImage::where('product_id', $product->id)->where('is_primary', false)->get();
            foreach ($existingNonPrimary as $old) {
                if ($old->image_path && ! str_starts_with($old->image_path, 'http') && Storage::disk('public')->exists($old->image_path)) {
                    Storage::disk('public')->delete($old->image_path);
                }
                $old->delete();
            }
            if ($mainFile === null) {
                // No main_image sent: replace primary too (backward compat), first of extraFiles = primary
                $oldPrimary = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
                if ($oldPrimary) {
                    if ($oldPrimary->image_path && ! str_starts_with($oldPrimary->image_path, 'http') && Storage::disk('public')->exists($oldPrimary->image_path)) {
                        Storage::disk('public')->delete($oldPrimary->image_path);
                    }
                    $oldPrimary->delete();
                }
            }
            $sortOrder = $mainFile !== null ? 1 : 0;
            foreach ($extraFiles as $index => $file) {
                $imagePath = $file->store('products', 'public');
                $this->compressProductImageIfNeeded($imagePath);
                $isPrimary = ($mainFile === null && $index === 0);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder++,
                    'is_primary' => $isPrimary,
                ]);
                if ($isPrimary) {
                    $updateData['image'] = $imagePath;
                }
            }
        }

        // Optional: add image URLs (multipart image_urls or image_url[])
        if ($request->has('image_urls') && is_array($request->image_urls)) {
            $maxOrder = (int) ProductImage::where('product_id', $product->id)->max('sort_order');
            foreach ($request->image_urls as $i => $url) {
                if (is_string($url) && $url !== '') {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $url,
                        'sort_order' => $maxOrder + 1 + $i,
                        'is_primary' => false,
                    ]);
                }
            }
        }

        $product->update($updateData);

        // Sync product.image to primary image when we have product_images (ensures response has correct image_url)
        $primaryImage = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
        if ($primaryImage && $product->image !== $primaryImage->image_path) {
            $product->update(['image' => $primaryImage->image_path]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            // Reload from DB so response shows all updated values (name, description, price, image, etc.)
            $fresh = Product::with(['category', 'images', 'primaryImage'])->find($product->id);
            $updatedFields = array_keys($updateData);
            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.',
                'updated_fields' => $updatedFields,
                'data' => $this->productToApiData($fresh),
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Delete a product
     */
    public function destroy(Request $request, $id)
    {
        if ($err = $this->invalidProductIdResponse($id, $request)) {
            return $err;
        }
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Delete image
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product deleted successfully.'
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        $query = Product::with('category');

        // Apply filters if any
        if ($request->has('search') && $request->search) {
            $query->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('description', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        $filename = 'products_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($products) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers (matching import format)
            fputcsv($file, [
                'Name',
                'Description',
                'Category',
                'Price',
                'Stock',
                'Image Path'
            ]);

            // Add product data (category may be null when product has no category)
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->description ?? '',
                    $product->category?->name ?? '',
                    $product->price,
                    $product->stock ?? 0,
                    $product->image ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show import form
     */
    public function showImport()
    {
        return view('admin.products.import');
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));
        
        // Remove header row
        $header = array_shift($data);
        
        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed

            // Skip empty rows
            if (empty(array_filter($row))) {
                $skipCount++;
                continue;
            }

            // Map CSV columns (Name, Description, Category, Price, Stock, Image Path)
            $name = isset($row[0]) && !empty(trim($row[0])) ? trim($row[0]) : null;
            $description = isset($row[1]) ? trim($row[1]) : '';
            $categoryName = isset($row[2]) && !empty(trim($row[2])) ? trim($row[2]) : null;
            $price = isset($row[3]) && !empty(trim($row[3])) ? trim($row[3]) : null;
            $stock = isset($row[4]) && !empty(trim($row[4])) ? intval(trim($row[4])) : 0;
            $imagePath = isset($row[5]) && !empty(trim($row[5])) ? trim($row[5]) : null;

            // Validate required fields
            if (empty($name)) {
                $errors[] = "Row {$rowNumber}: Name is required";
                continue;
            }

            if (empty($price) || !is_numeric($price)) {
                $errors[] = "Row {$rowNumber}: Valid price is required";
                continue;
            }

            // Find or create category
            $categoryId = null;
            if ($categoryName) {
                $category = Category::firstOrCreate(['name' => trim($categoryName)]);
                $categoryId = $category->id;
            }

            try {
                // Check if product already exists (by name)
                $existingProduct = Product::where('name', trim($name))->first();

                if ($existingProduct && !$request->has('update_existing')) {
                    $skipCount++;
                    continue;
                }

                $productData = [
                    'name' => trim($name),
                    'description' => trim($description),
                    'category_id' => $categoryId,
                    'price' => floatval($price),
                    'stock' => intval($stock),
                ];

                if ($existingProduct && $request->has('update_existing')) {
                    $existingProduct->update($productData);
                    $successCount++;
                } else {
                    Product::create($productData);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        $message = "Import completed. Success: {$successCount}, Skipped: {$skipCount}";
        if (!empty($errors)) {
            $message .= ", Errors: " . count($errors);
        }

        return redirect()->route('admin.products.index')
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $count = Product::whereIn('id', $request->product_ids)->delete();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "{$count} product(s) deleted successfully.",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "{$count} product(s) deleted successfully.");
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'status' => 'required|in:active,draft,archived',
        ]);

        $count = Product::whereIn('id', $request->product_ids)
            ->update(['status' => $request->status]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "Status updated for {$count} product(s).",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "Status updated for {$count} product(s).");
    }

    /**
     * Bulk update stock
     */
    public function bulkUpdateStock(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'stock' => 'required|integer|min:0',
        ]);

        $count = Product::whereIn('id', $request->product_ids)
            ->update(['stock' => $request->stock]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "Stock updated for {$count} product(s).",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "Stock updated for {$count} product(s).");
    }

    /**
     * Toggle product publish status.
     */
    public function toggleStatus(Request $request, $id)
    {
        if ($err = $this->invalidProductIdResponse($id, $request)) {
            return $err;
        }
        $product = Product::findOrFail($id);

        $newStatus = $product->status === 'active' ? 'draft' : 'active';
        $product->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Product published successfully.' : 'Product unpublished successfully.';

        if ($request->expectsJson() || $request->is('api/*')) {
            $product->refresh();
            $product->load(['category', 'images', 'primaryImage']);
            return response()->json([
                'status'  => true,
                'message' => $message,
                'data'    => $this->productToApiData($product),
            ]);
        }
        return redirect()->back()->with('success', $message);
    }
}
