<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Banner;
use App\Support\BannerLinkResolver;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Get active banners for customer home screen
     * Public endpoint - no authentication required
     */
    public function index(Request $request)
    {
        $banners = Banner::active()
            ->ordered()
            ->get()
            ->map(function ($banner) {
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
            });

        return ApiResponse::success('Banners retrieved successfully.', $banners);
    }
}
