<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\Service;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;

class ProductController extends Controller
{
    /** Product API allowed fields for create/update payload (plus images handled separately). */
    private const PRODUCT_API_FIELDS = [
        'name', 'description', 'price', 'stock', 'status', 'is_featured', 'category_id', 'weight_unit', 'sku', 'handle', 'product_type',
        'estimated_arrival', 'job_duration',
    ];

    /** Response keys for product API (allowed fields + id, image, image_url, main_image, gallery_images, category, timestamps). */
    private const PRODUCT_API_RESPONSE_KEYS = [
        'id', 'name', 'description', 'price', 'stock', 'status', 'is_featured', 'category_id', 'weight_unit', 'sku', 'handle',
        'estimated_arrival', 'job_duration',
        'image', 'image_url', 'main_image', 'gallery_images', 'category', 'created_at', 'updated_at',
    ];

    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Return product data with only allowed API fields.
     * Main image shown once (main_image + root image_url); gallery images separate (gallery_images only).
     */
    private function productToApiData(Product $product): array
    {
        $imagesCollection = $product->relationLoaded('images') ? $product->images : collect([]);
        $primaryImage = $product->relationLoaded('primaryImage') ? $product->primaryImage : null;
        $mainImage = null;
        $galleryImages = [];
        if ($primaryImage && $primaryImage->image_path) {
            $mainImage = [
                'id' => $primaryImage->id,
                'image_path' => $primaryImage->image_path,
                'image_url' => ProductImage::buildFullUrl($primaryImage->image_path),
            ];
        }
        $uniqueImages = ProductImage::uniqueByPath($imagesCollection);
        foreach ($uniqueImages as $img) {
            if ($img->is_primary) {
                if ($mainImage === null) {
                    $mainImage = [
                        'id' => $img->id,
                        'image_path' => $img->image_path,
                        'image_url' => ProductImage::buildFullUrl($img->image_path),
                    ];
                }
            } else {
                $galleryImages[] = [
                    'id' => $img->id,
                    'image_path' => $img->image_path,
                    'image_url' => ProductImage::buildFullUrl($img->image_path),
                    'sort_order' => (int) $img->sort_order,
                ];
            }
        }
        // Canonical image for API response should always follow resolved main image first.
        // This avoids stale `products.image` values when primary image was changed.
        $rootImagePath = $mainImage['image_path'] ?? $product->image;

        $imagesList = [];
        if ($mainImage !== null) {
            $imagesList[] = $mainImage;
        }
        foreach ($galleryImages as $galleryImage) {
            $imagesList[] = $galleryImage;
        }

        // Variable product extras (option groups + variants) — full group/option + image URLs
        $optionGroups = [];
        if ($product->relationLoaded('optionGroups')) {
            $optionGroups = $product->optionGroups
                ->sortBy('sort_order')
                ->values()
                ->map(fn (ProductOptionGroup $group) => $group->toApiArray())
                ->all();
        }

        $variants = [];
        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $optIds = [];
                if ($variant->relationLoaded('options')) {
                    $optIds = $variant->options->pluck('id')->values()->all();
                }
                $variants[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'stock' => $variant->stock,
                    'is_default' => (bool) $variant->is_default,
                    'label' => $variant->label,
                    'option_ids' => $optIds,
                ];
            }
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'product_type' => $product->product_type ?? 'simple',
            'price' => $product->price,
            'stock' => $product->stock,
            'status' => $product->status,
            'is_featured' => (bool) ($product->is_featured ?? false),
            'category_id' => $product->category_id,
            'weight_unit' => $product->weight_unit,
            'sku' => $product->sku,
            'handle' => $product->handle,
            'estimated_arrival' => $product->estimated_arrival,
            'job_duration' => $product->job_duration,
            'image' => $rootImagePath,
            'image_url' => ProductImage::buildFullUrl($rootImagePath),
            'main_image' => $mainImage,
            'gallery_images' => $galleryImages,
            'images' => $imagesList,
            'category' => $product->relationLoaded('category') ? $product->category : null,
            'service_ids' => $product->relationLoaded('services') ? $product->services->pluck('id')->values()->all() : $product->services()->pluck('id')->values()->all(),
            'option_groups' => $optionGroups,
            'variants' => $variants,
            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
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
     * Splits only on "\r\n--boundary" (line-boundary) so binary file content is NEVER split when
     * "--boundary" appears inside the file (which would truncate images to half-size).
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
        $filesMainImage = [];
        $filesImage = [];
        $filesMulti = [];
        $filesOptionImages = [];
        $parts = preg_split('/\r?\n--'.preg_quote($boundary, '/').'/', $raw) ?: [];
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
                } elseif ($part === $firstPrefix || str_starts_with($part, $firstPrefix)) {
                    $part = ltrim(substr($part, strlen($firstPrefix)), "\r\n");
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
            if (! preg_match('/(?:name)="([^"]+)"|(?:name)=\'([^\']+)\'/i', $headers, $nameMatch)) {
                continue;
            }
            $name = $nameMatch[1] !== '' ? $nameMatch[1] : ($nameMatch[2] ?? '');
            $isFile = preg_match('/filename="([^"]*)"/', $headers, $fileMatch);
            if ($isFile) {
                $originalName = $fileMatch[1] !== '' ? $fileMatch[1] : 'file';
                $mimeType = null;
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $ctMatch)) {
                    $mimeType = trim($ctMatch[1]);
                }
                $tmpPath = tempnam(sys_get_temp_dir(), 'put_');
                if ($tmpPath !== false && file_put_contents($tmpPath, $value) !== false) {
                    if (($mimeType === null || $mimeType === '') && is_file($tmpPath)) {
                        $detected = mime_content_type($tmpPath);
                        $mimeType = is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
                    }
                    $uploaded = new UploadedFile($tmpPath, $originalName, $mimeType, \UPLOAD_ERR_OK, true);
                    if ($name === 'main_image') {
                        $filesMainImage[] = $uploaded;
                    } elseif ($name === 'image') {
                        $filesImage[] = $uploaded;
                    } elseif ($name === 'images' || $name === 'images[]') {
                        $filesMulti['images'] = $filesMulti['images'] ?? [];
                        $filesMulti['images'][] = $uploaded;
                    } elseif (preg_match('/^option_images\[([^\]]+)\]$/', $name, $optMatch)) {
                        $filesOptionImages[$optMatch[1]] = $uploaded;
                    } elseif ($name === 'option_images') {
                        $filesOptionImages[] = $uploaded;
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
        if ($filesMainImage !== []) {
            $request->files->set('main_image', $filesMainImage);
        }
        if ($filesImage !== []) {
            $request->files->set('image', $filesImage);
        }
        foreach ($filesMulti as $key => $fileArray) {
            $request->files->set($key, $fileArray);
            if ($key === 'images') {
                $request->files->set('images[]', $fileArray);
            }
        }
        if ($filesOptionImages !== []) {
            $existingOptionFiles = $request->file('option_images');
            $merged = is_array($existingOptionFiles) ? $existingOptionFiles : [];
            foreach ($filesOptionImages as $key => $file) {
                if (is_int($key)) {
                    $merged[(string) $key] = $file;
                } else {
                    $merged[$key] = $file;
                }
            }
            $request->files->set('option_images', $merged);
        }
    }

    /**
     * Compress image if over 5 MB (all image uploads in project).
     */
    private function compressProductImageIfNeeded(string $relativePath): void
    {
        \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($relativePath);
    }

    /**
     * Validation rule for main_image: accept single file or array of files (each image|mimes).
     */
    private function mainImageValidationRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if ($value === null) {
                return;
            }
            $files = is_array($value) ? $value : [$value];
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            foreach ($files as $f) {
                if (! $f instanceof \Illuminate\Http\UploadedFile) {
                    $fail(__('The main image must be a valid image file.'));
                    return;
                }
                if (! $f->isValid()) {
                    $fail(__('The main image file is invalid.'));
                    return;
                }
                $mime = $f->getMimeType();
                if (! in_array($mime, $allowed, true)) {
                    $fail(__('The main image must be a file of type: jpeg, jpg, png, webp.'));
                    return;
                }
            }
        };
    }

    /**
     * Normalize main_image input: if multiple files sent as main_image (or main_image[]), first = primary, rest = extra.
     * Returns [mainFile|null, extraFilesFromMain[]].
     */
    private function normalizeMainImageInput(Request $request): array
    {
        $mainFile = null;
        $extraFromMain = [];
        if ($request->hasFile('main_image')) {
            $f = $request->file('main_image');
            if (is_array($f)) {
                $valid = array_values(array_filter($f, fn ($file) => $file && $file->isValid()));
                if (count($valid) > 0) {
                    $mainFile = $valid[0];
                    $extraFromMain = array_slice($valid, 1);
                }
            } elseif ($f && $f->isValid()) {
                $mainFile = $f;
            }
        }
        if ($mainFile === null && $request->hasFile('image')) {
            $f = $request->file('image');
            if (is_array($f)) {
                $valid = array_values(array_filter($f, fn ($file) => $file && $file->isValid()));
                if (count($valid) > 0) {
                    $mainFile = $valid[0];
                    $extraFromMain = array_slice($valid, 1);
                }
            } elseif ($f && $f->isValid()) {
                $mainFile = $f;
            }
        }
        return [$mainFile, $extraFromMain];
    }

    /**
     * Renumber product_images sort_order: primary=0, then 1,2,3... so main image and gallery display consistently.
     */
    private function reorderProductImages(int $productId): void
    {
        $images = ProductImage::where('product_id', $productId)->orderByRaw('is_primary DESC')->orderBy('sort_order')->get();
        foreach ($images as $index => $img) {
            if ((int) $img->sort_order !== $index) {
                $img->update(['sort_order' => $index]);
            }
        }
    }

    /**
     * List products (with search, category filter, pagination).
     * Optimized: minimal eager loading, direct response building.
     */
    public function index(Request $request)
    {
        $isApi = $request->expectsJson() || $request->is('api/*');
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        // Optimized: only load what's needed
        $query = Product::with([
            'category:id,name,slug',
            'primaryImage:id,product_id,image_path,is_primary',
            'firstImage:id,product_id,image_path,sort_order',
        ]);
        
        // Add extra relations only for API requests (so variable option groups are available in JSON).
        if ($isApi) {
            $query->with([
                'images:id,product_id,image_path,sort_order,is_primary',
                'optionGroups.options',
                'variants.options',
            ]);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $filter = $request->get('filter', 'all');
        if ($filter === 'active') {
            $query->where('stock', '>', 0);
        } elseif ($filter === 'draft' || $filter === 'archived') {
            $query->where('stock', '<=', 0);
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);
        $products = $query->orderByDesc('id')->paginate($perPage);

        if ($isApi) {
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

        $categories = Category::select('id', 'name')->get();
        return view('admin.products.index', compact('products', 'categories'));
    }

    /**
     * Create a product.
     * Same endpoint for (1) JSON body: product fields + optional image_urls (array of URLs).
     * (2) Multipart/form-data: product fields as form fields + image files in images[] (or image for single)
     *     + optional image_urls (JSON string) or image_url[] (repeated) to merge with file uploads.
     * Auth: Authorization: Bearer {{admin_token}}.
     */
    /**
     * Save option groups (and their options) from the JSON blob submitted by the admin UI.
     * Replaces all existing groups for this product when variable; clears them when simple.
     */
    /**
     * option_groups_json (string) or option_groups (array) from JSON / multipart.
     */
    private function normalizeOptionGroupsJsonInput(Request $request): ?string
    {
        if ($request->has('option_groups_json')) {
            $value = $request->input('option_groups_json');
            if (is_array($value)) {
                return json_encode($value);
            }
            if (is_string($value)) {
                return $value;
            }
        }

        if ($request->has('option_groups')) {
            $value = $request->input('option_groups');
            if (is_array($value)) {
                return json_encode($value);
            }
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function decodeOptionGroupsPayload(?string $json): ?array
    {
        if ($json === null) {
            return null;
        }

        $json = trim($json);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Collect option image uploads (multipart). Keys: temp_key, option id, opt_{id}.
     *
     * @return array<string, UploadedFile>
     */
    private function collectOptionImageFilesFromRequest(Request $request): array
    {
        $files = [];

        $bag = $request->file('option_images');
        if (is_array($bag)) {
            foreach ($bag as $key => $file) {
                $this->addOptionImageFileToMap($files, (string) $key, $file);
            }
        } elseif ($bag instanceof UploadedFile) {
            $this->addOptionImageFileToMap($files, '0', $bag);
        }

        foreach ($request->allFiles() as $name => $file) {
            if (preg_match('/^option_images\[([^\]]+)\]$/', (string) $name, $matches)) {
                $this->addOptionImageFileToMap($files, $matches[1], $file);
            }
        }

        // Fallback: some clients / PUT parsers leave files only in $_FILES
        if ($files === [] && isset($_FILES['option_images']) && is_array($_FILES['option_images']['name'])) {
            foreach ($_FILES['option_images']['name'] as $key => $originalName) {
                if (! is_string($originalName) || $originalName === '') {
                    continue;
                }
                $tmpName = $_FILES['option_images']['tmp_name'][$key] ?? null;
                $error = (int) ($_FILES['option_images']['error'][$key] ?? UPLOAD_ERR_NO_FILE);
                $mime = $_FILES['option_images']['type'][$key] ?? null;
                if (! is_string($tmpName) || ! is_uploaded_file($tmpName) || $error !== UPLOAD_ERR_OK) {
                    continue;
                }
                $symfony = new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $tmpName,
                    $originalName,
                    is_string($mime) ? $mime : null,
                    $error,
                    true
                );
                $this->addOptionImageFileToMap($files, (string) $key, UploadedFile::createFromBase($symfony));
            }
        }

        return $files;
    }

    /**
     * @param  array<string, UploadedFile>  $files
     */
    private function addOptionImageFileToMap(array &$files, string $key, mixed $file): void
    {
        if ($this->isUsableOptionImageUpload($file)) {
            $files[$key] = $file;

            return;
        }

        if (! is_array($file)) {
            return;
        }

        foreach ($file as $nestedKey => $nestedFile) {
            $resolvedKey = is_string($nestedKey) ? $nestedKey : $key;
            $this->addOptionImageFileToMap($files, $resolvedKey, $nestedFile);
        }
    }

    /**
     * @param  array<string, UploadedFile>  $optionImageFiles
     */
    private function resolveOptionImageFileFromKeys(?int $optionId, ?string $tempKey, array $optionImageFiles): ?UploadedFile
    {
        $candidates = [];
        if (is_string($tempKey) && trim($tempKey) !== '') {
            $candidates[] = trim($tempKey);
        }
        if ($optionId !== null && $optionId > 0) {
            $candidates[] = (string) $optionId;
            $candidates[] = 'opt_'.$optionId;
            $candidates[] = 'option_'.$optionId;
        }

        foreach ($candidates as $key) {
            if (! isset($optionImageFiles[$key])) {
                continue;
            }
            $file = $optionImageFiles[$key];
            if ($this->isUsableOptionImageUpload($file)) {
                return $file;
            }
        }

        foreach ($optionImageFiles as $fileKey => $file) {
            if (! $this->isUsableOptionImageUpload($file)) {
                continue;
            }
            if ($tempKey !== null && $tempKey !== '' && strcasecmp((string) $fileKey, $tempKey) === 0) {
                return $file;
            }
            if ($optionId !== null && $optionId > 0) {
                $idStr = (string) $optionId;
                if ($fileKey === $idStr || $fileKey === 'opt_'.$idStr || $fileKey === 'option_'.$idStr) {
                    return $file;
                }
            }
        }

        return null;
    }

    private function isUsableOptionImageUpload(mixed $file): bool
    {
        if (! $file instanceof UploadedFile) {
            return false;
        }

        if ($file->isValid()) {
            return true;
        }

        $path = $file->getPathname();

        return is_string($path) && $path !== '' && is_file($path) && filesize($path) > 0;
    }

    /**
     * When client uploads option_images[...], drop stale image_path/image_url from JSON (Postman often copies GET).
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<string, UploadedFile>  $optionImageFiles
     * @return array<int, array<string, mixed>>
     */
    private function stripOptionImageFieldsWhenNewFilesPresent(array $groups, array $optionImageFiles): array
    {
        if ($optionImageFiles === []) {
            return $groups;
        }

        foreach ($groups as $groupIndex => $groupData) {
            foreach ($groupData['options'] ?? [] as $optionIndex => $optionData) {
                $optionId = isset($optionData['id']) && is_numeric($optionData['id']) ? (int) $optionData['id'] : 0;
                if ($optionId <= 0) {
                    $optionId = (int) ($this->parseOptionIdFromTempKey($optionData['temp_key'] ?? null) ?? 0);
                }
                $tempKey = isset($optionData['temp_key']) && is_string($optionData['temp_key']) ? $optionData['temp_key'] : null;
                $file = $this->resolveOptionImageFileFromKeys(
                    $optionId > 0 ? $optionId : null,
                    $tempKey,
                    $optionImageFiles
                );
                if (! $file || ! $this->isUsableOptionImageUpload($file)) {
                    continue;
                }

                unset(
                    $groups[$groupIndex]['options'][$optionIndex]['image_path'],
                    $groups[$groupIndex]['options'][$optionIndex]['image_url'],
                    $groups[$groupIndex]['options'][$optionIndex]['existing_image_path']
                );
            }
        }

        return $groups;
    }

    /**
     * Link uploaded option_images[...] keys to options in JSON (id, temp_key, or single file per group).
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @param  array<string, UploadedFile>  $optionImageFiles
     * @return array<int, array<string, mixed>>
     */
    private function bindUploadedOptionImagesToGroupsPayload(array $groups, array $optionImageFiles): array
    {
        if ($optionImageFiles === []) {
            return $groups;
        }

        $assignedKeys = [];

        foreach ($groups as $groupIndex => $groupData) {
            foreach ($groupData['options'] ?? [] as $optionIndex => $optionData) {
                $optionId = isset($optionData['id']) && is_numeric($optionData['id']) ? (int) $optionData['id'] : 0;
                $tempKey = isset($optionData['temp_key']) ? trim((string) $optionData['temp_key']) : '';

                foreach ($optionImageFiles as $fileKey => $file) {
                    if (in_array($fileKey, $assignedKeys, true) || ! $this->isUsableOptionImageUpload($file)) {
                        continue;
                    }

                    $matchesId = $optionId > 0 && in_array($fileKey, [
                        (string) $optionId,
                        'opt_'.$optionId,
                        'option_'.$optionId,
                    ], true);
                    $matchesTempKey = $tempKey !== '' && strcasecmp($fileKey, $tempKey) === 0;

                    if ($matchesId || $matchesTempKey) {
                        $groups[$groupIndex]['options'][$optionIndex]['temp_key'] = $fileKey;
                        $assignedKeys[] = $fileKey;
                        break;
                    }
                }
            }
        }

        foreach ($groups as $groupIndex => $groupData) {
            $remainingKeys = array_values(array_diff(array_keys($optionImageFiles), $assignedKeys));
            if (count($remainingKeys) !== 1) {
                continue;
            }

            $fileKey = $remainingKeys[0];
            foreach ($groupData['options'] ?? [] as $optionIndex => $optionData) {
                $hasImage = ! empty($optionData['image_path'])
                    || ! empty($optionData['image_url'])
                    || ! empty($optionData['existing_image_path']);
                if (! $hasImage) {
                    $groups[$groupIndex]['options'][$optionIndex]['temp_key'] = $fileKey;
                    $assignedKeys[] = $fileKey;
                    break;
                }
            }
        }

        return $groups;
    }

    /**
     * Fill missing option ids / image paths from DB so option sync does not drop images.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  array<string, UploadedFile>  $optionImageFiles
     */
    private function enrichOptionGroupsPayload(Product $product, array $groups, array $optionImageFiles = []): array
    {
        $existingGroups = $product->optionGroups()->with('options')->get();
        $groupsById = $existingGroups->keyBy('id');
        $groupsByName = $existingGroups->keyBy(fn ($group) => $this->optionLookupKey($group->name));

        foreach ($groups as $groupIndex => $groupData) {
            $existingGroup = null;
            $groupId = isset($groupData['id']) && is_numeric($groupData['id']) ? (int) $groupData['id'] : 0;
            if ($groupId > 0 && $groupsById->has($groupId)) {
                $existingGroup = $groupsById->get($groupId);
            } elseif (! empty($groupData['name'])) {
                $existingGroup = $groupsByName->get($this->optionLookupKey($groupData['name']));
            }

            if ($existingGroup) {
                $groups[$groupIndex]['id'] = $groups[$groupIndex]['id'] ?? $existingGroup->id;
                if (empty($groups[$groupIndex]['name'])) {
                    $groups[$groupIndex]['name'] = $existingGroup->name;
                }
            }

            $optionsById = $existingGroup
                ? $existingGroup->options->keyBy('id')
                : collect();
            $optionsByLabel = $existingGroup
                ? $existingGroup->options->keyBy(fn ($opt) => $this->optionLookupKey($opt->label))
                : collect();

            foreach ($groupData['options'] ?? [] as $optionIndex => $optionData) {
                $existingOption = null;
                $optionId = isset($optionData['id']) && is_numeric($optionData['id']) ? (int) $optionData['id'] : 0;
                if ($optionId <= 0) {
                    $optionId = (int) ($this->parseOptionIdFromTempKey($optionData['temp_key'] ?? null) ?? 0);
                }
                if ($optionId > 0 && $optionsById->has($optionId)) {
                    $existingOption = $optionsById->get($optionId);
                } elseif (! empty($optionData['label'])) {
                    $existingOption = $optionsByLabel->get($this->optionLookupKey($optionData['label']));
                }

                if (! $existingOption) {
                    continue;
                }

                if (empty($groups[$groupIndex]['options'][$optionIndex]['id'])) {
                    $groups[$groupIndex]['options'][$optionIndex]['id'] = $existingOption->id;
                }
                if (empty($groups[$groupIndex]['options'][$optionIndex]['temp_key'])) {
                    $groups[$groupIndex]['options'][$optionIndex]['temp_key'] = 'opt_'.$existingOption->id;
                }

                $resolvedId = (int) $groups[$groupIndex]['options'][$optionIndex]['id'];
                $tempKey = $groups[$groupIndex]['options'][$optionIndex]['temp_key'] ?? null;
                $incomingFile = $optionImageFiles !== []
                    ? $this->resolveOptionImageFileFromKeys($resolvedId, is_string($tempKey) ? $tempKey : null, $optionImageFiles)
                    : null;
                $hasNewUpload = $incomingFile && $this->isUsableOptionImageUpload($incomingFile);

                $hasImagePath = ! empty($groups[$groupIndex]['options'][$optionIndex]['image_path'])
                    || ! empty($groups[$groupIndex]['options'][$optionIndex]['existing_image_path']);
                $hasImageUrl = ! empty($groups[$groupIndex]['options'][$optionIndex]['image_url']);

                if ($hasNewUpload) {
                    unset(
                        $groups[$groupIndex]['options'][$optionIndex]['image_path'],
                        $groups[$groupIndex]['options'][$optionIndex]['image_url'],
                        $groups[$groupIndex]['options'][$optionIndex]['existing_image_path']
                    );
                } elseif (! $hasImagePath && ! $hasImageUrl && $existingOption->image_path) {
                    $groups[$groupIndex]['options'][$optionIndex]['image_path'] = $existingOption->image_path;
                    $groups[$groupIndex]['options'][$optionIndex]['image_url'] = $existingOption->image_url;
                }
            }
        }

        return $groups;
    }

    /**
     * Update only option images (no full option-group rebuild) when client sends files without JSON.
     *
     * @param  array<string, UploadedFile>  $optionImageFiles
     */
    private function patchOptionImagesFromUploads(Product $product, array $optionImageFiles): void
    {
        if ($product->product_type !== 'variable' || $optionImageFiles === []) {
            return;
        }

        $product->loadMissing('optionGroups.options');

        foreach ($product->optionGroups as $group) {
            foreach ($group->options as $option) {
                $file = $this->resolveOptionImageFileFromKeys(
                    (int) $option->id,
                    'opt_'.$option->id,
                    $optionImageFiles
                );
                if (! $file) {
                    continue;
                }

                $path = $this->storeOptionImageFile($file);
                if ($path === null) {
                    continue;
                }

                if ($option->image_path
                    && $option->image_path !== $path
                    && ! str_starts_with($option->image_path, 'http')
                    && Storage::disk('public')->exists($option->image_path)) {
                    Storage::disk('public')->delete($option->image_path);
                }

                $option->update(['image_path' => $path]);
            }
        }
    }

    private function storeOptionImageFile(UploadedFile $file): ?string
    {
        if (! $this->isUsableOptionImageUpload($file)) {
            return null;
        }

        $path = $file->store('product-options', 'public');
        if (! is_string($path) || $path === '') {
            return null;
        }

        $this->compressProductImageIfNeeded($path);

        return $path;
    }

    /**
     * Sync or patch variable-product options from the request (multipart-safe).
     */
    private function syncProductOptionGroupsFromRequest(Product $product, Request $request): void
    {
        $product->refresh();
        $productType = (string) ($request->input('product_type', $product->product_type) ?? 'simple');

        if ($productType !== 'variable') {
            if ($productType === 'simple') {
                $product->optionGroups()->delete();
            }

            return;
        }

        if ($product->product_type !== 'variable') {
            $product->update(['product_type' => 'variable']);
            $product->refresh();
        }

        $optionImageFiles = $this->collectOptionImageFilesFromRequest($request);
        $json = $this->normalizeOptionGroupsJsonInput($request);

        // Apply uploaded files to existing options first (PUT/POST multipart) so DB has paths before JSON sync.
        if ($optionImageFiles !== []) {
            $this->patchOptionImagesFromUploads($product, $optionImageFiles);
        }

        if ($json !== null) {
            $groups = $this->decodeOptionGroupsPayload($json);
            if ($groups !== null && $groups !== []) {
                $groups = $this->stripOptionImageFieldsWhenNewFilesPresent($groups, $optionImageFiles);
                $groups = $this->bindUploadedOptionImagesToGroupsPayload($groups, $optionImageFiles);
                $enriched = $this->enrichOptionGroupsPayload($product, $groups, $optionImageFiles);
                $this->syncOptionGroupsFromJson($product, json_encode($enriched), $optionImageFiles);

                // Safety net: re-apply files after sync in case JSON sync missed a match.
                if ($optionImageFiles !== []) {
                    $this->patchOptionImagesFromUploads($product, $optionImageFiles);
                }

                return;
            }
        }
    }

    private function deleteStoredOptionImage(?string $imagePath): void
    {
        if (! $imagePath || str_starts_with($imagePath, 'http')) {
            return;
        }

        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }

    /**
     * @return array{by_id: array<int, string>, by_label: array<string, string>}
     */
    private function collectExistingOptionImagePaths(Product $product): array
    {
        $byId = [];
        $byLabel = [];

        $product->optionGroups()
            ->with('options:id,product_option_group_id,label,image_path')
            ->get()
            ->each(function ($group) use (&$byId, &$byLabel): void {
                $groupKey = $this->optionLookupKey($group->name);
                foreach ($group->options as $opt) {
                    $path = is_string($opt->image_path) ? trim($opt->image_path) : '';
                    if ($path === '') {
                        continue;
                    }
                    $byId[(int) $opt->id] = $path;
                    $byLabel[$groupKey.'|'.$this->optionLookupKey($opt->label)] = $path;
                }
            });

        return ['by_id' => $byId, 'by_label' => $byLabel];
    }

    private function optionLookupKey(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /**
     * Parse numeric option id from GET-style temp_key (e.g. opt_417 → 417).
     */
    private function parseOptionIdFromTempKey(mixed $tempKey): ?int
    {
        if (! is_string($tempKey)) {
            return null;
        }

        $tempKey = trim($tempKey);
        if ($tempKey === '' || ! preg_match('/^opt_(\d+)$/i', $tempKey, $matches)) {
            return null;
        }

        $id = (int) $matches[1];

        return $id > 0 ? $id : null;
    }

    /**
     * Normalize image_path from JSON or derive relative storage path from image_url.
     */
    private function normalizeStoredOptionImagePath(?string $value, bool $fromUrl = false): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (! $fromUrl && ! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            return ltrim(str_replace('\\', '/', $value), '/');
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (str_contains($path, 'product-options/')) {
            return substr($path, strpos($path, 'product-options/'));
        }

        if (str_contains($path, '/media/')) {
            $afterMedia = substr($path, strpos($path, '/media/') + 7);

            return ltrim($afterMedia, '/');
        }

        return null;
    }

    private function syncOptionGroupsFromJson(Product $product, ?string $json, ?array $optionImageFiles = null): void
    {
        if ($product->product_type !== 'variable' || blank($json)) {
            // Simple product: remove any leftover groups
            if ($product->product_type === 'simple') {
                $product->optionGroups()->delete();
            }

            return;
        }

        $groups = json_decode($json, true);
        if (! is_array($groups)) {
            return;
        }

        $existingOptionImagePaths = $this->collectExistingOptionImagePaths($product);

        DB::transaction(function () use ($product, $groups, $optionImageFiles, $existingOptionImagePaths) {
            $keptGroupIds = [];

            foreach ($groups as $gi => $groupData) {
                if (empty($groupData['name'])) {
                    continue;
                }

                $groupId = isset($groupData['id']) && is_numeric($groupData['id']) ? (int) $groupData['id'] : 0;
                $group = $groupId > 0
                    ? $product->optionGroups()->where('id', $groupId)->first()
                    : null;

                $groupAttrs = [
                    'name'        => $groupData['name'],
                    'subtitle'    => $groupData['subtitle'] ?? null,
                    'input_type'  => $groupData['input_type'] ?? 'single',
                    'is_required' => $groupData['is_required'] ?? true,
                    'sort_order'  => $groupData['sort_order'] ?? $gi,
                ];

                if ($group) {
                    $group->update($groupAttrs);
                } else {
                    $group = $product->optionGroups()->create($groupAttrs);
                }

                $keptGroupIds[] = $group->id;
                $keptOptionIds = [];

                foreach ($groupData['options'] ?? [] as $oi => $opt) {
                    if (empty($opt['label'])) {
                        continue;
                    }

                    $resolvedImagePath = $this->resolveOptionImagePath(
                        $opt,
                        $optionImageFiles,
                        $existingOptionImagePaths,
                        (string) $groupData['name']
                    );

                    $optionId = isset($opt['id']) && is_numeric($opt['id']) ? (int) $opt['id'] : 0;
                    if ($optionId <= 0) {
                        $optionId = (int) ($this->parseOptionIdFromTempKey($opt['temp_key'] ?? null) ?? 0);
                    }
                    $option = $optionId > 0
                        ? $group->options()->where('id', $optionId)->first()
                        : null;

                    if ($resolvedImagePath === null && $option?->image_path) {
                        $resolvedImagePath = $option->image_path;
                    }

                    $optionAttrs = [
                        'label'          => $opt['label'],
                        'subtitle'       => $opt['subtitle'] ?? null,
                        'price_modifier' => $opt['price_modifier'] ?? 0,
                        'sort_order'     => $opt['sort_order'] ?? $oi,
                    ];

                    if ($option) {
                        if ($resolvedImagePath !== null) {
                            if ($resolvedImagePath !== $option->image_path) {
                                $this->deleteStoredOptionImage($option->image_path);
                            }
                            $optionAttrs['image_path'] = $resolvedImagePath;
                        }
                        $option->update($optionAttrs);
                    } else {
                        $optionAttrs['image_path'] = $resolvedImagePath;
                        $option = $group->options()->create($optionAttrs);
                    }

                    $keptOptionIds[] = $option->id;
                }

                $group->options()->whereNotIn('id', $keptOptionIds)->get()->each(function (ProductOption $orphan): void {
                    $this->deleteStoredOptionImage($orphan->image_path);
                    $orphan->delete();
                });
            }

            $product->optionGroups()->whereNotIn('id', $keptGroupIds)->with('options')->get()->each(function (ProductOptionGroup $orphanGroup): void {
                foreach ($orphanGroup->options as $orphanOption) {
                    $this->deleteStoredOptionImage($orphanOption->image_path);
                    $orphanOption->delete();
                }
                $orphanGroup->delete();
            });
        });
    }

    /**
     * Resolve option image from uploaded file (preferred) or existing path in JSON / DB.
     */
    private function resolveOptionImagePath(
        array $optionData,
        ?array $optionImageFiles = null,
        array $existingOptionImagePaths = [],
        string $groupName = ''
    ): ?string {
        $optionId = isset($optionData['id']) && is_numeric($optionData['id']) ? (int) $optionData['id'] : null;
        $tempKey = isset($optionData['temp_key']) && is_string($optionData['temp_key']) ? $optionData['temp_key'] : null;
        if (($optionId === null || $optionId <= 0) && $tempKey !== null) {
            $optionId = $this->parseOptionIdFromTempKey($tempKey);
        }
        $file = is_array($optionImageFiles)
            ? $this->resolveOptionImageFileFromKeys($optionId, $tempKey, $optionImageFiles)
            : null;
        if ($file) {
            $stored = $this->storeOptionImageFile($file);
            if ($stored !== null) {
                return $stored;
            }
        }

        foreach (['image_path', 'existing_image_path'] as $key) {
            $stored = $this->normalizeStoredOptionImagePath($optionData[$key] ?? null);
            if ($stored !== null) {
                return $stored;
            }
        }

        $fromUrl = $this->normalizeStoredOptionImagePath($optionData['image_url'] ?? null, true);
        if ($fromUrl !== null) {
            return $fromUrl;
        }

        $resolvedOptionId = $optionId ?? 0;
        if ($resolvedOptionId > 0 && isset($existingOptionImagePaths['by_id'][$resolvedOptionId])) {
            return $existingOptionImagePaths['by_id'][$resolvedOptionId];
        }

        $labelKey = $this->optionLookupKey($groupName).'|'.$this->optionLookupKey($optionData['label'] ?? '');
        if ($labelKey !== '|' && isset($existingOptionImagePaths['by_label'][$labelKey])) {
            return $existingOptionImagePaths['by_label'][$labelKey];
        }

        return null;
    }

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
            'is_featured' => 'nullable|boolean',
            'category_id' => 'nullable|integer',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'sku'         => 'nullable|string|max:255|unique:products,sku',
            'handle'      => 'nullable|string|max:255|unique:products,handle',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'main_image'  => ['nullable', $this->mainImageValidationRule()],
            'main_image.*'=> 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp',
            'image_urls'  => 'nullable|array',
            'image_urls.*'=> 'nullable|string|url',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'service_id'    => 'nullable|integer|exists:services,id',
            'estimated_arrival'   => 'nullable|string|max:255',
            'job_duration'        => 'nullable|string|max:255',
            'product_type'        => 'nullable|in:simple,variable',
            'option_groups_json'  => 'nullable',
            'option_groups'       => 'nullable',
            'option_images'       => 'nullable|array',
            'option_images.*'     => 'nullable|image|mimes:jpg,jpeg,png,webp',
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
        $createData['is_featured'] = $request->has('is_featured') ? $request->boolean('is_featured') : false;
        $createData['weight_unit'] = $createData['weight_unit'] ?? $validated['weight_unit'] ?? 'kg';
        $createData['stock'] = $createData['stock'] ?? $validated['stock'] ?? 0;
        $createData['product_type'] = $request->input('product_type', 'simple');
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

        // Handle images: main_image (single or multiple) = first is primary, rest go to images; images[] = extra
        [$mainFile, $extraFromMain] = $this->normalizeMainImageInput($request);
        $extraFiles = $extraFromMain;
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
        $this->reorderProductImages($product->id);

        // Optional: link product to services (accept single service_id or array service_ids)
        $serviceIds = [];
        if ($request->filled('service_id')) {
            $serviceIds = [(int) $request->service_id];
        } elseif ($request->has('service_ids') && is_array($request->service_ids)) {
            $serviceIds = array_values(array_filter(array_map('intval', $request->service_ids)));
        }
        if (! empty($serviceIds)) {
            $product->services()->sync($serviceIds);
        }

        $this->syncProductOptionGroupsFromRequest($product, $request);

        // Check if this is an API request – same response shape as update/detail (main_image, gallery_images, no duplication)
        if ($request->expectsJson() || $request->is('api/*')) {
            $product->refresh();
            $product->load(['category', 'services', 'images', 'primaryImage', 'optionGroups.options', 'variants.options']);
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
        $product = Product::with(['category', 'services', 'images', 'optionGroups.options'])->findOrFail($id);
        $categories = Category::orderBy('name')->get();
        $services = Service::with('category')->orderBy('name')->get();
        return view('admin.products.edit', compact('product', 'categories', 'services'));
    }

    /**
     * Show a single product.
     * Same response shape as add/update: data.main_image + data.gallery_images (no duplication).
     */
    public function show(Request $request, $id)
    {
        if ($err = $this->invalidProductIdResponse($id, $request)) {
            return $err;
        }
        $product = Product::with(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options'])->findOrFail($id);
        
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
        $categories = Category::orderBy('name')->get();
        $services = Service::with('category')->orderBy('name')->get();
        return view('admin.products.create', compact('categories', 'services'));
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
            'is_featured' => 'nullable|boolean',
            'category_id' => 'nullable|integer',
            'weight_unit' => 'nullable|in:kg,g,lb,oz',
            'sku'         => 'nullable|string|max:255|unique:products,sku,' . $id,
            'handle'      => 'nullable|string|max:255|unique:products,handle,' . $id,
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'main_image'  => ['nullable', $this->mainImageValidationRule()],
            'main_image.*'=> 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp',
            'image_urls'  => 'nullable|array',
            'image_urls.*'=> 'nullable|string|url',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'service_id'    => 'nullable|integer|exists:services,id',
            'estimated_arrival'  => 'nullable|string|max:255',
            'job_duration'       => 'nullable|string|max:255',
            'product_type'       => 'nullable|in:simple,variable',
            'option_groups_json' => 'nullable',
            'option_groups'      => 'nullable',
            'option_images'      => 'nullable|array',
            'option_images.*'    => 'nullable|image|mimes:jpg,jpeg,png,webp',
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

        foreach (['estimated_arrival', 'job_duration'] as $timingKey) {
            if (array_key_exists($timingKey, $updateData) && $updateData[$timingKey] === '') {
                $updateData[$timingKey] = null;
            }
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

        // Smooth partial image update: only change what you send.
        // - Only main_image → replace primary image only; keep existing gallery.
        // - Only images[] → replace gallery only; keep existing main image.
        // - Both → replace all (new primary + new gallery).
        [$mainFile, $extraFromMain] = $this->normalizeMainImageInput($request);
        $extraFiles = $extraFromMain;
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
        $seenPaths = [];
        $extraFiles = array_values(array_filter($extraFiles, function ($f) use (&$seenPaths) {
            $path = $f->getRealPath();
            if ($path && isset($seenPaths[$path])) {
                return false;
            }
            if ($path) {
                $seenPaths[$path] = true;
            }
            return true;
        }));

        $hasMain = $mainFile !== null;
        $hasGallery = $extraFiles !== [];
        $existingImages = ProductImage::where('product_id', $product->id)->orderByRaw('is_primary DESC')->orderBy('sort_order')->get();
        $primaryImage = $existingImages->firstWhere('is_primary', true);
        $galleryImages = $existingImages->filter(fn ($img) => ! $img->is_primary)->values();

        if ($hasMain && $hasGallery) {
            // Replace all: remove every existing image, add new primary + new gallery
            foreach ($existingImages as $old) {
                if ($old->image_path && ! str_starts_with($old->image_path, 'http') && Storage::disk('public')->exists($old->image_path)) {
                    Storage::disk('public')->delete($old->image_path);
                }
                $old->delete();
            }
            $sortOrder = 0;
            $imagePath = $mainFile->store('products', 'public');
            $this->compressProductImageIfNeeded($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder++,
                'is_primary' => true,
            ]);
            $updateData['image'] = $imagePath;
            foreach ($extraFiles as $file) {
                $imagePath = $file->store('products', 'public');
                $this->compressProductImageIfNeeded($imagePath);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder++,
                    'is_primary' => false,
                ]);
            }
        } elseif ($hasMain) {
            // Replace primary only: remove old primary, add new primary; keep gallery
            if ($primaryImage) {
                if ($primaryImage->image_path && ! str_starts_with($primaryImage->image_path, 'http') && Storage::disk('public')->exists($primaryImage->image_path)) {
                    Storage::disk('public')->delete($primaryImage->image_path);
                }
                $primaryImage->delete();
            }
            $imagePath = $mainFile->store('products', 'public');
            $this->compressProductImageIfNeeded($imagePath);
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => 0,
                'is_primary' => true,
            ]);
            $updateData['image'] = $imagePath;
            foreach ($galleryImages as $index => $img) {
                $img->update(['sort_order' => $index + 1]);
            }
        } elseif ($hasGallery) {
            // Replace gallery only: remove non-primary images, add new gallery; keep main image
            foreach ($galleryImages as $old) {
                if ($old->image_path && ! str_starts_with($old->image_path, 'http') && Storage::disk('public')->exists($old->image_path)) {
                    Storage::disk('public')->delete($old->image_path);
                }
                $old->delete();
            }
            $sortOrder = 1;
            foreach ($extraFiles as $file) {
                $imagePath = $file->store('products', 'public');
                $this->compressProductImageIfNeeded($imagePath);
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder++,
                    'is_primary' => false,
                ]);
            }
            // If there was no primary image before, promote first newly uploaded image as primary
            // so admin listing cards and API always have a canonical thumbnail.
            if (! $primaryImage) {
                $newPrimary = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
                if ($newPrimary) {
                    $newPrimary->update(['is_primary' => true, 'sort_order' => 0]);
                    $updateData['image'] = $newPrimary->image_path;
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

        // Optional: sync product to services (accept single service_id or array service_ids)
        $serviceIds = [];
        if ($request->filled('service_id')) {
            $serviceIds = [(int) $request->service_id];
        } elseif ($request->has('service_ids') && is_array($request->service_ids)) {
            $serviceIds = array_values(array_filter(array_map('intval', $request->service_ids)));
        }
        $product->services()->sync($serviceIds);

        // Variable options: same pipeline as store() — product_type then option groups + images
        if ($request->has('product_type')) {
            $product->update(['product_type' => $request->input('product_type', 'simple')]);
            $product->refresh();
        }
        $this->syncProductOptionGroupsFromRequest($product, $request);

        // Final pass: ensure option image files are applied (Postman / mobile multipart)
        $product->refresh();
        if ($product->product_type === 'variable') {
            $lateOptionFiles = $this->collectOptionImageFilesFromRequest($request);
            if ($lateOptionFiles !== []) {
                $this->patchOptionImagesFromUploads($product, $lateOptionFiles);
            }
        }

        // Sync product.image to primary image when we have product_images (ensures response has correct image_url)
        $primaryImage = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
        if ($primaryImage && $product->image !== $primaryImage->image_path) {
            $product->update(['image' => $primaryImage->image_path]);
        }
        // Renumber sort_order so primary=0, others 1,2,3... (ensures main + gallery display consistently)
        $this->reorderProductImages($product->id);

        if ($request->expectsJson() || $request->is('api/*')) {
            // Reload from DB so response shows all updated values (name, description, price, image, etc.)
            $fresh = Product::with(['category', 'services', 'images', 'primaryImage', 'optionGroups.options', 'variants.options'])->find($product->id);
            $updatedFields = array_keys($updateData);
            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.',
                'updated_fields' => $updatedFields,
                'data' => $this->productToApiData($fresh),
            ]);
        }
        return redirect()->route('admin.products.show', $product)
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
            $product->load(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options']);
            return response()->json([
                'status'  => true,
                'message' => $message,
                'data'    => $this->productToApiData($product),
            ]);
        }
        return redirect()->back()->with('success', $message);
    }
}
