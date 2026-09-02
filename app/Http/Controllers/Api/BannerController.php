<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\BannerCache;
use App\Support\BannerLinkResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BannerController extends Controller
{
    /**
     * Get active banners for customer home screen
     * Public endpoint - no authentication required
     */
    public function index(Request $request)
    {
        $banners = Cache::remember(
            BannerCache::PUBLIC_LIST_KEY,
            BannerCache::PUBLIC_LIST_TTL_SECONDS,
            static function () {
                return Banner::active()
                    ->ordered()
                    ->get()
                    ->map(static function ($banner) {
                        return [
                            'id' => $banner->id,
                            'title' => $banner->title,
                            'description' => $banner->description,
                            'button_text' => $banner->button_text,
                            'image_url' => $banner->image_url,
                            'action_type' => $banner->action_type,
                            'action_value' => $banner->action_value ?: $banner->link,
                            'href' => $banner->resolved_href,
                            'is_external' => $banner->resolved_href_is_external,
                            'priority' => $banner->priority,
                        ];
                    })
                    ->values()
                    ->all();
            }
        );

        return ApiResponse::success('Banners retrieved successfully.', $banners)
            ->header('Cache-Control', 'public, max-age=120, stale-while-revalidate=600');
    }
}
