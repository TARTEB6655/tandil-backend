<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Banner;
use Illuminate\Http\Request;
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
     * Create a new banner. Multipart only: image (required), title, description, button_text, button_link (single URL), priority, is_active.
     * button_link = optional URL; when set, button opens this link. When empty, no action.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'button_link' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $buttonLink = $request->input('button_link');
        $buttonLink = $buttonLink ? trim($buttonLink) : null;

        $imagePath = $request->file('image')->store('banners', 'public');

        $banner = Banner::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'link' => $buttonLink,
            'action_type' => $buttonLink ? 'link' : 'none',
            'action_value' => $buttonLink,
            'button_text' => $request->button_text,
            'priority' => (int) ($request->priority ?? 0),
            'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
        ]);

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
     * Update a banner. Multipart only: image (optional), title, description, button_text, button_link (single URL), priority, is_active.
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
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

        if ($request->hasFile('image')) {
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('banners', 'public');
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
