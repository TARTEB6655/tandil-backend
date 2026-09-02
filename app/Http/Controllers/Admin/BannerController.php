<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\BannerCache;
use App\Support\BannerLinkResolver;
use App\Services\ImageCompressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $banners = Banner::ordered()->get();
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'button_link' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $linkFields = BannerLinkResolver::parseAdminButtonLink($request->button_link);
        $imagePath = $request->file('image')->store('banners', 'public');
        ImageCompressionService::compressHomeBannerFromPublicPath($imagePath);

        $banner = Banner::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $imagePath,
            'link' => $linkFields['link'],
            'action_type' => $linkFields['action_type'],
            'action_value' => $linkFields['action_value'],
            'button_text' => $request->button_text,
            'priority' => $request->priority ?? 0,
            'is_active' => $request->has('is_active') ? (bool)$request->is_active : true,
        ]);

        BannerCache::forgetPublicList();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner created successfully');
    }

    public function edit($id)
    {
        $banner = Banner::findOrFail($id);
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp',
            'button_link' => 'nullable|string|max:500',
            'button_text' => 'nullable|string|max:100',
            'priority' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $linkFields = $request->has('button_link')
            ? BannerLinkResolver::parseAdminButtonLink($request->button_link)
            : [
                'action_type' => $banner->action_type ?? 'none',
                'action_value' => $banner->action_value,
                'link' => $banner->link,
            ];
        $data = [
            'title' => $request->title,
            'description' => $request->description,
            'link' => $linkFields['link'],
            'action_type' => $linkFields['action_type'],
            'action_value' => $linkFields['action_value'],
            'button_text' => $request->button_text,
            'priority' => $request->priority ?? $banner->priority,
            'is_active' => $request->has('is_active') ? (bool)$request->is_active : $banner->is_active,
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($banner->image && Storage::disk('public')->exists($banner->image)) {
                Storage::disk('public')->delete($banner->image);
            }
            $data['image'] = $request->file('image')->store('banners', 'public');
            ImageCompressionService::compressHomeBannerFromPublicPath($data['image']);
        }

        $banner->update($data);
        BannerCache::forgetPublicList();

        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner updated successfully');
    }

    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);
        
        // Delete image
        if ($banner->image && Storage::disk('public')->exists($banner->image)) {
            Storage::disk('public')->delete($banner->image);
        }
        
        $banner->delete();
        BannerCache::forgetPublicList();
        
        return redirect()->route('admin.banners.index')
            ->with('success', 'Banner deleted successfully');
    }

    /**
     * Update banner order (priority)
     */
    public function updateOrder(Request $request)
    {
        $request->validate([
            'banners' => 'required|array',
            'banners.*.id' => 'required|exists:banners,id',
            'banners.*.priority' => 'required|integer',
        ]);

        foreach ($request->banners as $bannerData) {
            Banner::where('id', $bannerData['id'])
                ->update(['priority' => $bannerData['priority']]);
        }

        BannerCache::forgetPublicList();

        return response()->json([
            'success' => true,
            'message' => 'Banner order updated successfully'
        ]);
    }

    /**
     * Toggle banner status
     */
    public function toggleStatus($id)
    {
        $banner = Banner::findOrFail($id);
        $banner->is_active = !$banner->is_active;
        $banner->save();
        BannerCache::forgetPublicList();

        return response()->json([
            'success' => true,
            'message' => 'Banner status updated successfully',
            'is_active' => $banner->is_active
        ]);
    }
}





