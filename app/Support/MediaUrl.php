<?php

namespace App\Support;

/**
 * Absolute /media URLs for any public-disk path (products, maintenance_photos, banners, …).
 * Thumbs reuse MediaThumbCache via ?w= on the Laravel /media route.
 */
final class MediaUrl
{
    public static function full(?string $imagePath): ?string
    {
        if (! $imagePath || ! is_string($imagePath)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', $imagePath), '/');
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'media/')) {
            $path = substr($path, strlen('media/'));
        }

        $mediaPath = 'media/'.$path;
        if (function_exists('request') && request() && request()->getHttpHost()) {
            return rtrim(request()->getSchemeAndHttpHost(), '/').'/'.$mediaPath;
        }

        $base = rtrim((string) config('app.url', ''), '/');

        return $base !== '' ? ($base.'/'.$mediaPath) : asset($mediaPath);
    }

    public static function thumb(?string $imagePath, int $width = 384): ?string
    {
        $full = self::full($imagePath);
        if ($full === null) {
            return null;
        }

        // Remote absolute URLs cannot use our /media?w= cache.
        if (preg_match('#^https?://#i', (string) $imagePath) === 1) {
            return $full;
        }

        $width = max(48, min(640, $width));
        $sep = str_contains($full, '?') ? '&' : '?';

        return $full.$sep.'w='.$width;
    }
}
