<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class BannerCache
{
    public const PUBLIC_LIST_KEY = 'api.banners.active.v1';

    public const PUBLIC_LIST_TTL_SECONDS = 900;

    public static function forgetPublicList(): void
    {
        Cache::forget(self::PUBLIC_LIST_KEY);
    }
}
