<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Banner;
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
            ->map(function($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->title,
                    'image_url' => $banner->image_url,
                    'action_type' => $banner->action_type,
                    'action_value' => $banner->action_value ?: $banner->link,
                    'priority' => $banner->priority,
                ];
            });

        return ApiResponse::success('Banners retrieved successfully.', $banners);
    }
}
