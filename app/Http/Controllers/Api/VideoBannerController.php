<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\VideoBanner;
use Illuminate\Http\Request;

class VideoBannerController extends Controller
{
    /**
     * Active video banners for the customer home screen ("Featured Video").
     * Public endpoint - no authentication required (mirrors GET /api/banners).
     */
    public function index(Request $request)
    {
        $videoBanners = VideoBanner::active()
            ->ordered()
            ->get()
            ->map(function (VideoBanner $videoBanner) {
                return [
                    'id' => $videoBanner->id,
                    'title' => $videoBanner->title,
                    'video_url' => $videoBanner->video_url,
                    'poster_url' => $videoBanner->poster_url,
                    'badge_text' => $videoBanner->badge_text,
                    'button_text' => $videoBanner->button_text,
                    'button_link' => $videoBanner->button_link,
                    'is_active' => $videoBanner->is_active,
                    'priority' => $videoBanner->priority,
                ];
            });

        return ApiResponse::success('Video banners retrieved successfully.', $videoBanners);
    }
}
