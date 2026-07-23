<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class VideoBannerCache
{
    public const PUBLIC_LIST_KEY = 'api.video_banners.active.v1';

    public const PUBLIC_LIST_TTL_SECONDS = 300;

    public static function forgetPublicList(): void
    {
        Cache::forget(self::PUBLIC_LIST_KEY);
    }
}
