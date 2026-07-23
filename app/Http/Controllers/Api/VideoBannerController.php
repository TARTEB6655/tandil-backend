<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VideoBanner;
use App\Support\VideoBannerCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VideoBannerController extends Controller
{
    /**
     * Active video banners for the customer home screen ("Featured Video").
     * Public endpoint - no authentication required (mirrors GET /api/banners).
     * Cached briefly so login/home does not hit DB on every open.
     */
    public function index(Request $request)
    {
        $videoBanners = Cache::remember(
            VideoBannerCache::PUBLIC_LIST_KEY,
            VideoBannerCache::PUBLIC_LIST_TTL_SECONDS,
            function () {
                return VideoBanner::active()
                    ->ordered()
                    ->get()
                    ->map(function (VideoBanner $videoBanner) {
                        return [
                            'id' => $videoBanner->id,
                            'title' => $videoBanner->title,
                            'video_url' => $videoBanner->video_url,
                            'badge_text' => $videoBanner->badge_text,
                            'button_text' => $videoBanner->button_text,
                            'is_active' => $videoBanner->is_active,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return ApiResponse::success('Video banners retrieved successfully.', $videoBanners)
            ->header('Cache-Control', 'public, max-age=60');
    }
}
