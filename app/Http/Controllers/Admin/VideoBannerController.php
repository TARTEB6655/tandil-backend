<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VideoBanner;
use App\Services\VideoCompressionService;
use App\Support\VideoBannerCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoBannerController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index()
    {
        $videoBanners = VideoBanner::ordered()->get();

        return view('admin.video-banners.index', compact('videoBanners'));
    }

    public function create()
    {
        return view('admin.video-banners.create');
    }

    public function store(Request $request)
    {
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $request->validate([
            'title' => 'nullable|string|max:255',
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/webm,video/ogg,video/x-m4v|max:30720',
            'badge_text' => 'nullable|string|max:100',
            'button_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ], [
            'video.required' => 'Please upload a video file.',
            'video.max' => 'Video must be 30MB or smaller. Please upload a smaller file.',
            'video.mimetypes' => 'Video must be an MP4, MOV, WebM, OGG, or M4V file.',
        ]);

        $stored = $this->storeVideoWithPoster($request->file('video'));
        if (! $stored['video_path']) {
            return back()->withInput()->withErrors(['video' => 'Failed to store video file.']);
        }

        try {
            VideoBanner::create([
                'title' => $request->input('title'),
                'video_path' => $stored['video_path'],
                'poster_path' => $stored['poster_path'],
                'badge_text' => $request->input('badge_text'),
                'button_text' => $request->input('button_text'),
                'is_active' => $request->boolean('is_active', true),
            ]);
        } catch (\Throwable $e) {
            $this->deleteStoredFile($stored['video_path']);
            $this->deleteStoredFile($stored['poster_path']);
            Log::error('Admin video banner create failed', ['error' => $e->getMessage()]);

            return back()->withInput()->withErrors(['video' => 'Could not create video banner. Please try again.']);
        }

        VideoBannerCache::forgetPublicList();

        return redirect()->route('admin.video-banners.index')
            ->with('success', 'Video banner created successfully.');
    }

    public function edit($id)
    {
        $videoBanner = VideoBanner::findOrFail($id);

        return view('admin.video-banners.edit', compact('videoBanner'));
    }

    public function update(Request $request, $id)
    {
        @set_time_limit(120);
        @ini_set('memory_limit', '512M');

        $videoBanner = VideoBanner::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'video' => 'nullable|file|mimetypes:video/mp4,video/quicktime,video/webm,video/ogg,video/x-m4v|max:30720',
            'badge_text' => 'nullable|string|max:100',
            'button_text' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ], [
            'video.max' => 'Video must be 30MB or smaller. Please upload a smaller file.',
            'video.mimetypes' => 'Video must be an MP4, MOV, WebM, OGG, or M4V file.',
        ]);

        $data = [
            'title' => $request->input('title'),
            'badge_text' => $request->input('badge_text'),
            'button_text' => $request->input('button_text'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('video')) {
            $newStored = $this->storeVideoWithPoster($request->file('video'));
            if (! $newStored['video_path']) {
                return back()->withInput()->withErrors(['video' => 'Failed to store video file.']);
            }
            $this->deleteStoredFile($videoBanner->video_path);
            $this->deleteStoredFile($videoBanner->poster_path);
            $data['video_path'] = $newStored['video_path'];
            $data['poster_path'] = $newStored['poster_path'];
        }

        $videoBanner->update($data);
        VideoBannerCache::forgetPublicList();

        return redirect()->route('admin.video-banners.index')
            ->with('success', 'Video banner updated successfully.');
    }

    public function destroy($id)
    {
        $videoBanner = VideoBanner::findOrFail($id);
        $this->deleteStoredFile($videoBanner->video_path);
        $this->deleteStoredFile($videoBanner->poster_path);
        $videoBanner->delete();
        VideoBannerCache::forgetPublicList();

        return redirect()->route('admin.video-banners.index')
            ->with('success', 'Video banner deleted successfully.');
    }

    public function toggleStatus($id)
    {
        $videoBanner = VideoBanner::findOrFail($id);
        $videoBanner->is_active = ! $videoBanner->is_active;
        $videoBanner->save();
        VideoBannerCache::forgetPublicList();

        return response()->json([
            'success' => true,
            'message' => 'Video banner status updated successfully',
            'is_active' => $videoBanner->is_active,
        ]);
    }

    /**
     * @return array{video_path: ?string, poster_path: ?string}
     */
    private function storeVideoWithPoster($file): array
    {
        if (! $file || ! $file->isValid()) {
            return ['video_path' => null, 'poster_path' => null];
        }

        $path = $file->store('video_banners', 'public');

        try {
            $videoPath = VideoCompressionService::compressIfNeededFromPublicPath($path);
            $posterPath = VideoCompressionService::extractPosterFromPublicPath($videoPath);

            return ['video_path' => $videoPath, 'poster_path' => $posterPath];
        } catch (\Throwable $e) {
            Log::warning('Admin video banner: compression skipped', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return ['video_path' => $path, 'poster_path' => null];
        }
    }

    private function deleteStoredFile(?string $path): void
    {
        if (! $path) {
            return;
        }
        if (filter_var($path, FILTER_VALIDATE_URL) || str_starts_with($path, 'http')) {
            return;
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
