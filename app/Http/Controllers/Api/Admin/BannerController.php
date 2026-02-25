<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Banner;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * List all banners (admin). Customer app uses GET /api/banners for active only.
     */
    public function index(Request $request)
    {
        $banners = Banner::ordered()->get()->map(function ($banner) {
            return $this->bannerToArray($banner);
        });

        return ApiResponse::success('Banners retrieved successfully.', $banners);
    }

    /**
     * Create a new banner. Image optional; can add or change image on update.
     * Fields: image (optional), title, description, button_text, button_link (single URL), priority, is_active.
     */
    public function store(Request $request)
    {
        $isMultipart = str_contains($request->header('Content-Type', ''), 'multipart/form-data');
        if ($isMultipart) {
            $this->parseMultipartIntoRequest($request);
        }
        $this->normalizeImageFileToSingle($request, 'image');

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'button_link' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $buttonLink = $request->input('button_link');
        $buttonLink = $buttonLink ? trim((string) $buttonLink) : null;

        $imagePath = null;
        $imageFile = $this->getSingleImageFile($request, 'image');
        if ($imageFile && $imageFile->isValid()) {
            $imagePath = $imageFile->store('banners', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($imagePath);
        }

        try {
            $banner = Banner::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'image' => $imagePath,
                'link' => $buttonLink,
                'action_type' => $buttonLink ? 'link' : 'none',
                'action_value' => $buttonLink,
                'button_text' => $request->input('button_text'),
                'priority' => (int) ($request->input('priority') ?? 0),
                'is_active' => $request->has('is_active') ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN) : true,
            ]);
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            Log::error('Banner create failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        return ApiResponse::success('Banner created successfully.', $this->bannerToArray($banner), 201);
    }

    /**
     * Get a single banner by ID.
     */
    public function show($id)
    {
        $banner = Banner::findOrFail($id);
        return ApiResponse::success('Banner retrieved successfully.', $this->bannerToArray($banner));
    }

    /**
     * Parse multipart/form-data and merge form fields + image file into the request.
     * Needed so image upload works for PUT (PHP does not populate $_FILES for PUT) and for consistent behavior with POST.
     * Same approach as CategoryController.
     */
    private function parseMultipartIntoRequest(Request $request): void
    {
        $contentType = $request->header('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'multipart/form-data')) {
            return;
        }
        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/', $contentType, $m)) {
            return;
        }
        $boundary = trim($m[1] ?? $m[2]);
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
                $tmpPath = tempnam(sys_get_temp_dir(), 'putban_');
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
     * If the request sent multiple files for the given key (e.g. "2 files" in Postman), Laravel sets it as an array.
     * Normalize so request->file($key) is a single UploadedFile (first one) for validation and storage.
     */
    private function normalizeImageFileToSingle(Request $request, string $key): void
    {
        $file = $request->file($key);
        if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
            $request->files->set($key, $file[0]);
        }
    }

    /**
     * Get a single uploaded file for the given key. If the request sent multiple files (e.g. "2 files" in Postman),
     * Laravel may return an array; we use the first file so the API doesn't throw a 500.
     */
    private function getSingleImageFile(Request $request, string $key): ?UploadedFile
    {
        $file = $request->file($key);
        if ($file instanceof UploadedFile) {
            return $file;
        }
        if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
            return $file[0];
        }
        return null;
    }

    /**
     * Update a banner. Multipart only: image (optional), title, description, button_text, button_link (single URL), priority, is_active.
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $isMultipart = str_contains($request->header('Content-Type', ''), 'multipart/form-data');
        if ($isMultipart && ($request->isMethod('PUT') || $request->isMethod('PATCH') || $request->isMethod('POST'))) {
            $this->parseMultipartIntoRequest($request);
        }
        $this->normalizeImageFileToSingle($request, 'image');

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'button_link' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $buttonLink = $request->has('button_link')
            ? (trim((string) $request->input('button_link')) ?: null)
            : (trim((string) ($banner->action_value ?? $banner->link ?? '')) ?: null);

        $data = [
            'title' => $request->has('title') ? (string) $request->title : (string) ($banner->title ?? ''),
            'description' => $request->has('description') ? $request->description : $banner->description,
            'link' => $buttonLink,
            'action_type' => $buttonLink ? 'link' : 'none',
            'action_value' => $buttonLink,
            'button_text' => $request->has('button_text') ? (string) $request->button_text : (string) ($banner->button_text ?? ''),
            'priority' => $request->has('priority') ? (int) $request->priority : (int) ($banner->priority ?? 0),
            'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : (bool) $banner->is_active,
        ];

        $imageFile = $this->getSingleImageFile($request, 'image');
        if ($imageFile && $imageFile->isValid()) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $imageFile->store('banners', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($data['image']);
        }

        try {
            $banner->update($data);
        } catch (\Throwable $e) {
            Log::error('Banner update failed', ['banner_id' => $banner->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        return ApiResponse::success('Banner updated successfully.', $this->bannerToArray($banner->fresh()));
    }

    /**
     * Delete a banner and its image.
     */
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }

        $banner->delete();
        return ApiResponse::success('Banner deleted successfully.');
    }

    /**
     * Reorder banners. Body: { "banners": [ { "id": 1, "priority": 0 }, ... ] }
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:banners,id',
            'banners.*.priority' => 'required|integer',
        ]);

        foreach ($request->banners as $item) {
            Banner::where('id', $item['id'])->update(['priority' => (int) $item['priority']]);
        }

        $banners = Banner::ordered()->get()->map(fn ($b) => $this->bannerToArray($b));
        return ApiResponse::success('Banner order updated successfully.', $banners);
    }

    /**
     * Toggle banner enabled/disabled.
     */
    public function toggleStatus($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();

        return ApiResponse::success('Banner status updated successfully.', [
            'id' => $banner->id,
            'is_active' => $banner->is_active,
        ]);
    }

    private function bannerToArray(Banner $banner): array
    {
        return [
            'id' => $banner->id,
            'title' => $banner->title,
            'description' => $banner->description,
            'button_text' => $banner->button_text,
            'image' => $banner->image,
            'image_url' => $banner->image_url,
            'link' => $banner->link,
            'action_type' => $banner->action_type,
            'action_value' => $banner->action_value ?: $banner->link,
            'priority' => $banner->priority,
            'is_active' => $banner->is_active,
            'created_at' => $banner->created_at?->format('c'),
            'updated_at' => $banner->updated_at?->format('c'),
        ];
    }
}
