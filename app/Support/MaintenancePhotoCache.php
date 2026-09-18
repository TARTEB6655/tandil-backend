<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

final class MaintenancePhotoCache
{
    private const VERSION_KEY = 'api.maintenance_photos.version';

    public const PUBLIC_LIST_TTL_SECONDS = 900;

    public static function publicListKey(int $page, int $perPage): string
    {
        return 'api.maintenance_photos.v'.self::version().".p{$page}.pp{$perPage}";
    }

    public static function bumpVersion(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 3);

            return;
        }

        Cache::increment(self::VERSION_KEY);
    }

    private static function version(): int
    {
        // Default 3 invalidates pre-thumb list caches after deploy.
        return (int) Cache::get(self::VERSION_KEY, 3);
    }
}
