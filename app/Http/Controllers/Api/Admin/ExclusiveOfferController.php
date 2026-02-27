<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\ExclusiveOffer;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Admin API for Exclusive Offers – create, list, show, update, delete.
 * Auth: Bearer token, role admin.
 */
class ExclusiveOfferController extends Controller
{
    /**
     * List all exclusive offers (admin). Public app uses GET /api/exclusive-offers for active only.
     */
    public function index(Request $request)
    {
        $offers = ExclusiveOffer::ordered()->get()->map(fn ($o) => $this->offerToArray($o));
        return ApiResponse::success('Exclusive offers retrieved successfully.', $offers);
    }

    /**
     * Create a new exclusive offer. Body: title, description (optional), image (optional file),
     * discount_type (percentage|fixed_amount|buy_one_get_one), discount_value (optional),
     * applies_to (optional), start_date, end_date (optional), is_active (optional), sort_order (optional).
     * Supports multipart/form-data for image upload.
     */
    public function store(Request $request)
    {
        $this->parseMultipartIfNeeded($request, 'image');
        $this->normalizeImageFileToSingle($request, 'image');

        $productIds = $this->normalizeProductIds($request->input('product_ids'));
        $request->merge(['product_ids' => $productIds]);
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'discount_type' => 'required|in:percentage,fixed_amount,buy_one_get_one',
            'discount_value' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $imagePath = null;
        $imageFile = $this->getSingleImageFile($request, 'image');
        if ($imageFile && $imageFile->isValid()) {
            $imagePath = $imageFile->store('exclusive_offers', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($imagePath);
        }

        try {
            $offer = ExclusiveOffer::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'image' => $imagePath,
                'discount_type' => $request->input('discount_type'),
                'discount_value' => $request->filled('discount_value') ? (float) $request->input('discount_value') : null,
                'applies_to' => $request->input('applies_to'),
                'start_date' => $request->filled('start_date') ? $request->input('start_date') : null,
                'end_date' => $request->filled('end_date') ? $request->input('end_date') : null,
                'is_active' => $request->has('is_active') ? filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN) : true,
                'sort_order' => (int) ($request->input('sort_order') ?? 0),
            ]);
            $productIds = $this->normalizeProductIds($request->input('product_ids'));
            $offer->products()->sync($productIds);
        } catch (\Throwable $e) {
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            Log::error('ExclusiveOffer create failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return ApiResponse::success('Exclusive offer created successfully.', $this->offerToArray($offer), 201);
    }

    /**
     * Get a single offer by ID (admin – returns inactive/expired too).
     */
    public function show($id)
    {
        $offer = ExclusiveOffer::findOrFail($id);
        return ApiResponse::success('Exclusive offer retrieved successfully.', $this->offerToArray($offer));
    }

    /**
     * Update an exclusive offer. Same fields as store. Use multipart for image upload.
     */
    public function update(Request $request, $id)
    {
        $offer = ExclusiveOffer::findOrFail($id);
        $this->parseMultipartIfNeeded($request, 'image');
        $this->normalizeImageFileToSingle($request, 'image');
        if ($request->has('product_ids')) {
            $request->merge(['product_ids' => $this->normalizeProductIds($request->input('product_ids'))]);
        }

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'discount_type' => 'sometimes|in:percentage,fixed_amount,buy_one_get_one',
            'discount_value' => 'nullable|numeric|min:0',
            'applies_to' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        // Only update fields when explicitly sent; preserve existing for partial updates (e.g. image-only) so public API still shows offer
        $data = [
            'title' => $request->has('title') ? $request->title : $offer->title,
            'description' => $request->has('description') ? $request->description : $offer->description,
            'discount_type' => $request->has('discount_type') ? $request->discount_type : $offer->discount_type,
            'discount_value' => $request->has('discount_value') ? ($request->filled('discount_value') ? (float) $request->discount_value : null) : $offer->discount_value,
            'applies_to' => $request->has('applies_to') ? $request->applies_to : $offer->applies_to,
            'start_date' => $request->filled('start_date') ? $request->start_date : ($request->has('start_date') ? null : $offer->start_date),
            'end_date' => $request->filled('end_date') ? $request->end_date : ($request->has('end_date') ? null : $offer->end_date),
            'is_active' => $request->filled('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $offer->is_active,
            'sort_order' => $request->has('sort_order') ? (int) $request->sort_order : $offer->sort_order,
        ];

        $imageFile = $this->getSingleImageFile($request, 'image');
        if ($imageFile && $imageFile->isValid()) {
            if ($offer->image && Storage::disk('public')->exists($offer->image)) {
                Storage::disk('public')->delete($offer->image);
            }
            $data['image'] = $imageFile->store('exclusive_offers', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($data['image']);
        }

        $offer->update($data);
        if ($request->has('product_ids')) {
            $offer->products()->sync($this->normalizeProductIds($request->input('product_ids')));
        }
        return ApiResponse::success('Exclusive offer updated successfully.', $this->offerToArray($offer->fresh()));
    }

    /**
     * Delete an exclusive offer and its image.
     */
    public function destroy($id)
    {
        $offer = ExclusiveOffer::findOrFail($id);
        if ($offer->image && Storage::disk('public')->exists($offer->image)) {
            Storage::disk('public')->delete($offer->image);
        }
        $offer->delete();
        return ApiResponse::success('Exclusive offer deleted successfully.');
    }

    private function parseMultipartIfNeeded(Request $request, string $fileKey): void
    {
        $contentType = $request->header('Content-Type');
        if (! $contentType || ! str_contains($contentType, 'multipart/form-data')) {
            return;
        }
        if (! preg_match('/boundary=(?:"([^"]+)"|([^\s;]+))/', $contentType, $m)) {
            return;
        }
        $boundary = trim($m[1] ?? $m[2]);
        $raw = $request->attributes->get('_put_multipart_raw') ?? $request->getContent();
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
            if ($isFile && $name === $fileKey) {
                $trailingBoundary = "\r\n--" . $boundary . "--";
                if (str_ends_with($value, $trailingBoundary)) {
                    $value = substr($value, 0, -strlen($trailingBoundary));
                }
                $trailingBoundaryLf = "\n--" . $boundary . "--";
                if (str_ends_with($value, $trailingBoundaryLf)) {
                    $value = substr($value, 0, -strlen($trailingBoundaryLf));
                }
                $originalName = ($fileMatch[1] ?? '') !== '' ? $fileMatch[1] : 'file';
                $mimeType = null;
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $ctMatch)) {
                    $mimeType = trim($ctMatch[1]);
                }
                $tmpPath = tempnam(sys_get_temp_dir(), 'putoff_');
                if ($tmpPath !== false && file_put_contents($tmpPath, $value) !== false) {
                    $uploadedFile = new UploadedFile($tmpPath, $originalName, $mimeType, \UPLOAD_ERR_OK, true);
                } else {
                    if ($tmpPath !== false) {
                        @unlink($tmpPath);
                    }
                }
                continue;
            }
            if (! $isFile) {
                $params[$name] = $value;
            }
        }
        if ($params !== []) {
            $request->merge($params);
        }
        if ($uploadedFile !== null) {
            $request->files->set($fileKey, $uploadedFile);
        }
    }

    private function normalizeImageFileToSingle(Request $request, string $key): void
    {
        $file = $request->file($key);
        if (is_array($file) && isset($file[0]) && $file[0] instanceof UploadedFile) {
            $request->files->set($key, $file[0]);
        }
    }

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

    /** Normalize product_ids from form-data (comma-separated string) or JSON (array) to array of ints. */
    private function normalizeProductIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', array_filter($value)));
        }
        if (is_string($value) && $value !== '') {
            return array_values(array_map('intval', array_filter(explode(',', $value))));
        }
        return [];
    }

    private function offerToArray(ExclusiveOffer $offer): array
    {
        $productIds = $offer->relationLoaded('products')
            ? $offer->products->pluck('id')->values()->all()
            : $offer->products()->pluck('products.id')->values()->all();

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'description' => $offer->description,
            'image' => $offer->image,
            'image_url' => $offer->image_url,
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value !== null ? (float) $offer->discount_value : null,
            'applies_to' => $offer->applies_to,
            'start_date' => $offer->start_date?->format('Y-m-d'),
            'end_date' => $offer->end_date?->format('Y-m-d'),
            'is_active' => $offer->is_active,
            'sort_order' => $offer->sort_order,
            'product_ids' => $productIds,
            'created_at' => $offer->created_at?->format('c'),
            'updated_at' => $offer->updated_at?->format('c'),
        ];
    }
}
