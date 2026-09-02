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
     * Response is cached so the app gets JSON in milliseconds.
     */
    public function index(Request $request)
    {
        $videoBanners = Cache::remember(
            VideoBannerCache::PUBLIC_LIST_KEY,
            VideoBannerCache::PUBLIC_LIST_TTL_SECONDS,
            function () {
                return VideoBanner::query()
                    ->active()
                    ->ordered()
                    ->get()
                    ->map(static fn (VideoBanner $videoBanner) => $videoBanner->toPublicApiArray())
                    ->values()
                    ->all();
            }
        );

        return ApiResponse::success('Video banners retrieved successfully.', $videoBanners)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600')
            ->header('X-Accel-Buffering', 'no');
    }
}
