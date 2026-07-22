<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\VideoBanner;
use App\Services\ImageCompressionService;
use App\Services\VideoCompressionService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoBannerController extends Controller
{
    /** File input keys handled by this controller (multipart). */
    private const FILE_KEYS = ['video', 'poster'];

    /**
     * List all video banners (admin). Customer app uses GET /api/video-banners for active only.
     */
    public function index(Request $request)
    {
        $items = VideoBanner::ordered()->get()->map(fn (VideoBanner $vb) => $this->toArray($vb));

        return ApiResponse::success('Video banners retrieved successfully.', $items);
    }

    /**
     * Create a video banner.
     * Fields: video (file, required), poster (file), title, badge_text,
     * button_text, button_link, is_active.
     */
    public function store(Request $request)
    {
        // Large media uploads: allow enough time for receive + compress, keep response fast.
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $this->prepareRequest($request);
        $this->validatePayload($request);

        $videoFile = $this->getSingleFile($request, 'video');
        if (! $videoFile) {
            return ApiResponse::error('A video file is required.', 422);
        }

        $videoPath = $this->storeVideo($videoFile);
        if (! $videoPath) {
            return ApiResponse::error('Failed to store video file.', 500);
        }
        $posterPath = $this->storePoster($this->getSingleFile($request, 'poster'));

        try {
            $videoBanner = VideoBanner::create([
                'title' => $request->input('title'),
                'video_path' => $videoPath,
                'poster_path' => $posterPath,
                'badge_text' => $request->input('badge_text'),
                'button_text' => $request->input('button_text'),
                'button_link' => $request->input('button_link'),
                'is_active' => $request->has('is_active') ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN) : true,
            ]);
        } catch (\Throwable $e) {
            $this->deleteStoredFile($videoPath);
            $this->deleteStoredFile($posterPath);
            Log::error('Video banner create failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        return ApiResponse::success('Video banner created successfully.', $this->toArray($videoBanner), 201);
    }

    /**
     * Get a single video banner by ID.
     */
    public function show($id)
    {
        $videoBanner = VideoBanner::findOrFail($id);

        return ApiResponse::success('Video banner retrieved successfully.', $this->toArray($videoBanner));
    }

    /**
     * Update a video banner. Send files via POST /{id} (multipart). Only provided fields are changed.
     */
    public function update(Request $request, $id)
    {
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $videoBanner = VideoBanner::findOrFail($id);

        $this->prepareRequest($request);
        $this->validatePayload($request);

        $data = [];
        foreach (['title', 'badge_text', 'button_text', 'button_link'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->input($field);
            }
        }
        if ($request->has('is_active')) {
            $data['is_active'] = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
        }

        $videoFile = $this->getSingleFile($request, 'video');
        if ($videoFile) {
            $this->deleteStoredFile($videoBanner->video_path);
            $data['video_path'] = $this->storeVideo($videoFile);
        }

        $posterFile = $this->getSingleFile($request, 'poster');
        if ($posterFile) {
            $this->deleteStoredFile($videoBanner->poster_path);
            $data['poster_path'] = $this->storePoster($posterFile);
        }

        try {
            $videoBanner->update($data);
        } catch (\Throwable $e) {
            Log::error('Video banner update failed', ['id' => $videoBanner->id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        return ApiResponse::success('Video banner updated successfully.', $this->toArray($videoBanner->fresh()));
    }

    /**
     * Toggle active/inactive.
     */
    public function toggleStatus($id)
    {
        $videoBanner = VideoBanner::findOrFail($id);
        $videoBanner->is_active = ! $videoBanner->is_active;
        $videoBanner->save();

        return ApiResponse::success('Video banner status updated successfully.', [
            'id' => $videoBanner->id,
            'is_active' => $videoBanner->is_active,
        ]);
    }

    private function validatePayload(Request $request): void
    {
        // Keep under typical PHP post_max_size (40M) so uploads don't hang for 60s+ then fail.
        // Server compresses video (ffmpeg → ~720p) and poster (≤400KB) after upload.
        $request->validate([
            'title' => 'nullable|string|max:255',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/ogg,video/x-m4v|max:25600',
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'badge_text' => 'nullable|string|max:100',
            'button_text' => 'nullable|string|max:100',
            'button_link' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ]);
    }

    private function storeVideo(?UploadedFile $file): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $path = $file->store('video_banners', 'public');
        $compressed = VideoCompressionService::compressIfNeededFromPublicPath($path);

        return $compressed ?: $path;
    }

    private function storePoster(?UploadedFile $file): ?string
    {
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $path = $file->store('video_banners/posters', 'public');
        ImageCompressionService::compressVideoBannerPosterFromPublicPath($path);

        return $path;
    }

    /**
     * Delete a stored public-disk file. Skips external URLs.
     */
    private function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (filter_var($path, FILTER_VALIDATE_URL) || substr($path, 0, 4) === 'http') {
            return;
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Normalize multipart requests: parse PUT bodies (PHP does not populate files for PUT)
     * and collapse multi-file inputs down to a single UploadedFile per key.
     */
    private function prepareRequest(Request $request): void
    {
        $isMultipart = str_contains($request->header('Content-Type', ''), 'multipart/form-data');
        if ($isMultipart && ($request->isMethod('PUT') || $request->isMethod('PATCH'))) {
            $this->parseMultipartIntoRequest($request);
        }
        foreach (self::FILE_KEYS as $key) {
            $file = $request->file($key);
            if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
                $request->files->set($key, $file[0]);
            }
        }
    }

    private function getSingleFile(Request $request, string $key): ?UploadedFile
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
     * Parse multipart/form-data (fields + files) for PUT/PATCH into the request.
     * Handles the file keys in self::FILE_KEYS. Mirrors Api\Admin\BannerController.
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
        $uploadedFiles = [];
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
                if (! in_array($name, self::FILE_KEYS, true)) {
                    continue;
                }
                $originalName = $fileMatch[1] !== '' ? $fileMatch[1] : 'file';
                $mimeType = null;
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $ctMatch)) {
                    $mimeType = trim($ctMatch[1]);
                }
                $tmpPath = tempnam(sys_get_temp_dir(), 'putvb_');
                if ($tmpPath !== false && file_put_contents($tmpPath, $value) !== false) {
                    $uploadedFiles[$name] = new UploadedFile($tmpPath, $originalName, $mimeType, \UPLOAD_ERR_OK, true);
                } elseif ($tmpPath !== false) {
                    @unlink($tmpPath);
                }
                continue;
            }
            $params[$name] = $value;
        }
        if ($params !== []) {
            $request->merge($params);
        }
        foreach ($uploadedFiles as $key => $file) {
            $request->files->set($key, $file);
        }
    }

    private function toArray(VideoBanner $videoBanner): array
    {
        return [
            'id' => $videoBanner->id,
            'title' => $videoBanner->title,
            'video_url' => $videoBanner->video_url,
            'poster_url' => $videoBanner->poster_url,
            'badge_text' => $videoBanner->badge_text,
            'button_text' => $videoBanner->button_text,
            'button_link' => $videoBanner->button_link,
            'is_active' => $videoBanner->is_active,
        ];
    }
}
